const http = require("http");
const fs = require("fs");
const path = require("path");
const { WebSocketServer } = require("ws");

const PORT = Number(process.env.PORT || 8000);
const HOST = "0.0.0.0";
const APP_KEY = "realtime-job-key";
const ROOT = path.resolve(__dirname, "../..");


const LABELS = [
  "مطابقة فاتورة المورد",
  "إرسال إشعار للعميل",
  "أرشفة مستند العملية",
  "تحديث رصيد المخزون",
  "توليد كشف الحساب",
  "التحقق من عملية الدفع",
  "مزامنة بيانات المنتج",
  "إغلاق أمر الشغل",
  "حساب عمولة المندوب",
  "تصدير تقرير اليوم",
  "مراجعة مرتجع العميل",
  "تحديث حالة الشحنة",
];

const batches = new Map();
const clients = new Set();
let revision = 0;

function stamp() {
  revision += 1;
  return new Date().toISOString() + "|" + String(revision).padStart(8, "0");
}

function recalculate(batch) {
  let processed = 0;
  let failed = 0;
  let pending = 0;
  let processing = null;
  let lastSettled = null;

  for (const task of batch.tasks) {
    if (task.status === "completed") {
      processed += 1;
      lastSettled = task;
    } else if (task.status === "failed") {
      failed += 1;
      lastSettled = task;
    } else if (task.status === "processing") {
      pending += 1;
      processing = task;
    } else if (task.status !== "cancelled") {
      pending += 1;
    }
  }

  const total = batch.tasks.length;
  const done = processed + failed;
  const inFlight = processing ? 0.45 : 0;
  batch.processed = processed;
  batch.failed = failed;
  batch.pending = pending;
  batch.total = total;
  batch.progress = total === 0 ? (batch.status === "queued" ? 0 : 100) : Math.round(((done + inFlight) / total) * 100);
  batch.current = processing || lastSettled;
  batch.updated_at = stamp();

  if (batch.cancelled) {
    batch.status = "cancelled";
    batch.finished = true;
  } else if (batch.status === "failed") {
    batch.finished = true;
  } else if (pending === 0 && total > 0 && batch.status !== "queued") {
    batch.finished = true;
    batch.status = failed > 0 ? "finished_with_failures" : "finished";
    batch.progress = 100;
  } else if (batch.status === "queued") {
    batch.finished = false;
    batch.progress = 0;
  } else {
    batch.status = "processing";
    batch.finished = false;
  }

  if (!batch.finished && batch.progress >= 100) {
    batch.progress = 99;
  }

  return batch;
}

function publicSnapshot(batch) {
  const current = batch.current
    ? { key: batch.current.key, label: batch.current.label, status: batch.current.status, message: batch.current.message || null }
    : null;

  return {
    batch_id: batch.batch_id,
    name: batch.name,
    status: batch.status,
    finished: batch.finished,
    cancelled: batch.cancelled,
    progress: batch.progress,
    percentage: batch.progress,
    processed: batch.processed,
    pending: batch.pending,
    failed: batch.failed,
    total: batch.total,
    tasks: batch.tasks.map((task) => ({
      key: task.key,
      label: task.label,
      status: task.status,
      message: task.message || null,
    })),
    tasks_truncated: false,
    current,
    error: batch.error || null,
    work_batch_id: batch.batch_id,
    updated_at: batch.updated_at,
    data: current,
  };
}

function active() {
  return Array.from(batches.values())
    .sort((a, b) => String(b.updated_at).localeCompare(String(a.updated_at)))
    .slice(0, 20)
    .map(publicSnapshot);
}

function send(ws, event, data, channel) {
  if (ws.readyState !== 1) {
    return;
  }

  const message = { event, data: JSON.stringify(data ?? {}) };
  if (channel) {
    message.channel = channel;
  }
  ws.send(JSON.stringify(message));
}

function broadcast(batch) {
  const snapshot = publicSnapshot(recalculate(batch));
  const channels = ["job-batches", "job-batch." + batch.batch_id];
  const events = snapshot.finished ? ["progress", "finished"] : ["progress"];

  for (const client of clients) {
    for (const channel of channels) {
      if (!client.channels.has(channel)) {
        continue;
      }
      for (const event of events) {
        send(client.ws, event, snapshot, channel);
      }
    }
  }

  return snapshot;
}

function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

async function run(batch) {
  batch.status = "processing";
  broadcast(batch);

  for (const task of batch.tasks) {
    if (batch.cancelled) {
      break;
    }

    task.status = "processing";
    broadcast(batch);
    await sleep(task.duration_ms);

    if (batch.cancelled) {
      task.status = "cancelled";
      break;
    }

    if (task.should_fail) {
      task.status = "failed";
      task.message = "تعذّر إكمال هذه العملية";
    } else {
      task.status = "completed";
    }
    broadcast(batch);
  }

  if (batch.cancelled) {
    for (const task of batch.tasks) {
      if (task.status === "pending" || task.status === "processing") {
        task.status = "cancelled";
      }
    }
  }

  broadcast(batch);
}

function readBody(req) {
  return new Promise((resolve, reject) => {
    const chunks = [];
    req.on("data", (chunk) => chunks.push(chunk));
    req.on("end", () => {
      const raw = Buffer.concat(chunks).toString("utf8");
      if (!raw) {
        resolve({});
        return;
      }
      try {
        resolve(JSON.parse(raw));
      } catch (error) {
        reject(error);
      }
    });
    req.on("error", reject);
  });
}

function json(res, status, payload, head) {
  const body = JSON.stringify(payload);
  res.writeHead(status, {
    "Content-Type": "application/json; charset=utf-8",
    "Cache-Control": "no-store",
    "Content-Length": Buffer.byteLength(body),
  });
  res.end(head ? undefined : body);
}

function text(res, status, body, type, head) {
  res.writeHead(status, {
    "Content-Type": type,
    "Cache-Control": "no-store",
    "Content-Length": Buffer.byteLength(body),
  });
  res.end(head ? undefined : body);
}

const server = http.createServer(async (req, res) => {
  const url = new URL(req.url, "http://localhost");
  const head = req.method === "HEAD";
  const method = head ? "GET" : req.method;

  try {
    if (method === "GET" && (url.pathname === "/" || url.pathname === "/index.html")) {
      const html = fs.readFileSync(path.join(__dirname, "public/index.html"));
      res.writeHead(200, {
        "Content-Type": "text/html; charset=utf-8",
        "Cache-Control": "no-store",
        "Content-Length": html.length,
      });
      res.end(head ? undefined : html);
      return;
    }

    if (method === "GET" && url.pathname === "/realtime-job-batch/assets/realtime-progress.css") {
      text(res, 200, fs.readFileSync(path.join(ROOT, "resources/css/realtime-progress.css")), "text/css; charset=utf-8", head);
      return;
    }

    if (method === "GET" && url.pathname === "/realtime-job-batch/assets/realtime-progress.js") {
      text(res, 200, fs.readFileSync(path.join(ROOT, "resources/js/realtime-progress.js")), "application/javascript; charset=utf-8", head);
      return;
    }

    if (method === "GET" && url.pathname === "/realtime-job-batch/assets/demo-form.js") {
      text(res, 200, fs.readFileSync(path.join(ROOT, "resources/js/demo-form.js")), "application/javascript; charset=utf-8", head);
      return;
    }

    if (method === "GET" && url.pathname === "/realtime-job-batches") {
      json(res, 200, { data: active() }, head);
      return;
    }

    const show = url.pathname.match(/^\/realtime-job-batches\/([^/]+)$/);
    if (method === "GET" && show) {
      const batch = batches.get(decodeURIComponent(show[1]));
      if (!batch) {
        json(res, 404, { message: "Not found" });
        return;
      }
      json(res, 200, publicSnapshot(batch), head);
      return;
    }

    const cancel = url.pathname.match(/^\/realtime-job-batches\/([^/]+)\/cancel$/);
    if (req.method === "POST" && cancel) {
      const batch = batches.get(decodeURIComponent(cancel[1]));
      if (!batch) {
        json(res, 404, { message: "Not found" });
        return;
      }
      if (batch.finished) {
        json(res, 200, publicSnapshot(batch));
        return;
      }
      batch.cancelled = true;
      json(res, 200, broadcast(batch));
      return;
    }

    if (req.method === "POST" && url.pathname === "/realtime-job-batches/demo") {
      const body = await readBody(req);
      const name = String(body.name || "").trim();
      const count = Number(body.count);
      const pace = Number(body.pace_ms);
      const fail = Boolean(body.simulate_failure);

      if (!name || !Number.isInteger(count) || count < 1 || count > 40 || !Number.isInteger(pace) || pace < 200 || pace > 5000) {
        json(res, 422, { message: "تحقق من الاسم وعدد المهام وسرعة التنفيذ." });
        return;
      }

      const id = crypto.randomUUID();
      const failAt = fail ? Math.floor(count / 2) : -1;
      const batch = {
        batch_id: id,
        name,
        status: "queued",
        finished: false,
        cancelled: false,
        error: null,
        tasks: Array.from({ length: count }, (_, index) => ({
          key: id + "-" + index,
          label: LABELS[index % LABELS.length] + " #" + (index + 1),
          status: "pending",
          message: null,
          duration_ms: pace,
          should_fail: index === failAt,
        })),
      };
      recalculate(batch);
      batches.set(id, batch);
      broadcast(batch);
      run(batch).catch((error) => console.error(error));
      json(res, 200, { batch_id: id, progress: publicSnapshot(batch) });
      return;
    }

    json(res, 404, { message: "Not found" });
  } catch (error) {
    json(res, 500, { message: error.message });
  }
});

const wss = new WebSocketServer({ noServer: true });

server.on("upgrade", (req, socket, head) => {
  const url = new URL(req.url, "http://localhost");
  if (!url.pathname.startsWith("/app/" + APP_KEY)) {
    socket.destroy();
    return;
  }

  wss.handleUpgrade(req, socket, head, (ws) => {
    const client = { ws, channels: new Set() };
    clients.add(client);
    const socketId = Math.floor(Math.random() * 100000) + "." + Math.floor(Math.random() * 100000);
    send(ws, "pusher:connection_established", { socket_id: socketId, activity_timeout: 120 });
    console.log("reverb client connected", socketId);

    ws.on("message", (raw) => {
      let message;
      try {
        message = JSON.parse(raw.toString());
      } catch (error) {
        return;
      }

      const data = typeof message.data === "string" ? safeParse(message.data) : message.data || {};

      if (message.event === "pusher:ping") {
        send(ws, "pusher:pong", {});
        return;
      }

      if (message.event === "pusher:subscribe" && data.channel) {
        client.channels.add(data.channel);
        send(ws, "pusher_internal:subscription_succeeded", {}, data.channel);
        console.log("subscribed", data.channel);

        if (data.channel === "job-batches") {
          for (const batch of batches.values()) {
            send(ws, "progress", publicSnapshot(batch), data.channel);
          }
        }
      }
    });

    ws.on("close", () => {
      clients.delete(client);
    });
  });
});

function safeParse(value) {
  try {
    return JSON.parse(value);
  } catch (error) {
    return {};
  }
}

server.listen(PORT, HOST, () => {
  console.log("Queue progress preview on http://" + HOST + ":" + PORT);
});
