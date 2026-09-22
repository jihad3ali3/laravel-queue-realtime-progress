(function () {
  if (window.RealtimeJobProgress) {
    return;
  }

  const state = {
    batches: new Map(),
    selectedId: null,
    connection: "connecting",
    config: null,
    socket: null,
    pollTimer: null,
    lastEventAt: 0,
  };

  const labels = {
    queued: "في الطابور",
    processing: "قيد التنفيذ",
    finished: "اكتملت",
    finished_with_failures: "اكتملت مع أخطاء",
    failed: "توقفت",
    cancelled: "أُلغيت",
    pending: "بالانتظار",
    completed: "تمت",
  };

  function config() {
    if (state.config) {
      return state.config;
    }

    const node = document.getElementById("rjb-config");
    state.config = node ? JSON.parse(node.textContent) : {};
    return state.config;
  }

  function isLocalHost(host) {
    return !host || host === "127.0.0.1" || host === "localhost" || host === "0.0.0.0";
  }

  function socketOptions(cfg) {
    const securePage = location.protocol === "https:";
    const e2b = location.hostname.match(/^(\d+)-(.+\.e2b\.app)$/);
    const serverPort = Number(cfg.serverPort || 8080);

    if (cfg.sameOrigin) {
      return {
        wsHost: location.hostname,
        wsPort: securePage ? 443 : Number(location.port || 80),
        wssPort: securePage ? 443 : Number(location.port || 443),
        forceTLS: securePage,
      };
    }

    if (e2b && isLocalHost(cfg.host) && isLocalHost(cfg.serverHost)) {
      return {
        wsHost: serverPort + "-" + e2b[2],
        wsPort: 443,
        wssPort: 443,
        forceTLS: true,
      };
    }

    if (cfg.host) {
      const scheme = cfg.scheme || (securePage ? "https" : "http");
      const forceTLS = scheme === "https";
      const port = Number(cfg.port || (forceTLS ? 443 : serverPort));

      return {
        wsHost: cfg.host,
        wsPort: port,
        wssPort: port,
        forceTLS: forceTLS,
      };
    }

    if (isLocalHost(location.hostname)) {
      const scheme = cfg.scheme || cfg.serverScheme || "http";
      const forceTLS = scheme === "https";
      const port = Number(cfg.port || cfg.serverPort || (forceTLS ? 443 : 8080));

      return {
        wsHost: cfg.serverHost || "127.0.0.1",
        wsPort: port,
        wssPort: port,
        forceTLS: forceTLS,
      };
    }

    return {
      wsHost: location.hostname,
      wsPort: securePage ? 443 : Number(location.port || 80),
      wssPort: securePage ? 443 : Number(location.port || 443),
      forceTLS: securePage,
    };
  }

  function setConnection(next) {
    state.connection = next;
    document.querySelectorAll("[data-rjb-connection]").forEach(function (node) {
      node.textContent =
        next === "connected"
          ? "متصل بـ Reverb"
          : next === "unavailable"
            ? "انقطع Reverb، تحديث احتياطي"
            : "جاري الاتصال بـ Reverb…";
    });
    document.querySelectorAll("[data-rjb-dot]").forEach(function (node) {
      node.classList.toggle("is-on", next === "connected");
      node.classList.toggle("is-off", next === "unavailable");
    });
  }

  function snapshotList() {
    return Array.from(state.batches.values()).sort(function (a, b) {
      return String(b.updated_at || "").localeCompare(String(a.updated_at || ""));
    });
  }

  function upsert(snapshot, options) {
    if (!snapshot || !snapshot.batch_id) {
      return;
    }

    const existing = state.batches.get(snapshot.batch_id);
    if (existing && isStale(existing, snapshot)) {
      if (options && options.select) {
        state.selectedId = snapshot.batch_id;
        render();
      }
      return;
    }

    state.batches.set(snapshot.batch_id, snapshot);

    if ((options && options.select) || !state.selectedId || !state.batches.has(state.selectedId)) {
      state.selectedId = snapshot.batch_id;
    }

    render();
  }

  function select(batchId) {
    if (!state.batches.has(batchId)) {
      return;
    }

    state.selectedId = batchId;
    render();
  }

  function statusLabel(status) {
    return labels[status] || status || "";
  }

  function renderDock() {
    document.querySelectorAll("[data-rjb-taskbar]").forEach(function (dock) {
      const list = dock.querySelector("[data-rjb-list]");
      const count = dock.querySelector("[data-rjb-count]");
      const items = snapshotList();

      if (count) {
        count.textContent = String(items.filter(function (item) {
          return !item.finished;
        }).length);
      }

      if (!list) {
        return;
      }

      if (!items.length) {
        list.innerHTML = '<p class="rjb-empty">ما في عمليات في الطابور.</p>';
        return;
      }

      list.innerHTML = items
        .map(function (item) {
          const selected = item.batch_id === state.selectedId ? " is-selected" : "";
          return (
            '<button type="button" class="rjb-job' + selected + '" data-id="' + escapeAttr(item.batch_id) + '">' +
              '<span class="rjb-job__name">' + escapeHtml(item.name || "عملية") + "</span>" +
              '<span class="rjb-job__meta">' + statusLabel(item.status) + " " + Number(item.progress || 0) + "%</span>" +
              '<span class="rjb-job__track"><span class="rjb-job__fill" style="width:' + Number(item.progress || 0) + '%"></span></span>' +
            "</button>"
          );
        })
        .join("");
    });
  }

  function renderPanel() {
    const selected = state.batches.get(state.selectedId) || null;

    document.querySelectorAll("[data-rjb-panel]").forEach(function (panel) {
      const fixedId = panel.dataset.batchId;
      const snapshot = fixedId ? state.batches.get(fixedId) || null : selected;
      paintPanel(panel, snapshot);
    });
  }

  function paintPanel(panel, snapshot) {
    const title = panel.querySelector("[data-rjb-title]");
    const status = panel.querySelector("[data-rjb-status]");
    const percent = panel.querySelector("[data-rjb-percent] b");
    const bar = panel.querySelector("[data-rjb-bar]");
    const fill = panel.querySelector("[data-rjb-bar-fill]");
    const message = panel.querySelector("[data-rjb-message]");
    const cancel = panel.querySelector("[data-rjb-cancel]");
    const tasks = panel.querySelector("[data-rjb-tasks]");

    setText(panel, "[data-rjb-processed]", snapshot ? snapshot.processed : 0);
    setText(panel, "[data-rjb-pending]", snapshot ? snapshot.pending : 0);
    setText(panel, "[data-rjb-failed]", snapshot ? snapshot.failed : 0);
    setText(panel, "[data-rjb-total]", snapshot ? snapshot.total : 0);

    if (!snapshot) {
      if (title) title.textContent = "بانتظار عملية";
      if (status) status.textContent = "ابدأ معالجة حتى يظهر شريط التقدم هنا.";
      if (percent) percent.textContent = "0";
      if (fill) fill.style.width = "0%";
      if (bar) {
        bar.setAttribute("aria-valuenow", "0");
        bar.classList.remove("is-done", "is-bad");
      }
      if (message) message.textContent = "الشريط يتحدث مباشرة من أحداث Reverb.";
      if (cancel) cancel.hidden = true;
      if (tasks) tasks.innerHTML = "";
      return;
    }

    if (title) title.textContent = snapshot.name || "عملية";
    if (status) {
      const currentLabel = snapshot.current && snapshot.current.label && snapshot.status === "processing"
        ? " — " + snapshot.current.label
        : "";
      status.textContent = statusLabel(snapshot.status) + currentLabel;
    }
    if (percent) percent.textContent = String(snapshot.progress || 0);
    if (fill) fill.style.width = Number(snapshot.progress || 0) + "%";
    if (bar) {
      bar.setAttribute("aria-valuenow", String(snapshot.progress || 0));
      bar.classList.toggle("is-live", snapshot.status === "processing");
      bar.classList.toggle("is-done", snapshot.status === "finished");
      bar.classList.toggle("is-bad", snapshot.status === "failed" || snapshot.status === "finished_with_failures");
      bar.classList.toggle("is-stopped", snapshot.status === "cancelled");
    }
    if (message) {
      message.textContent = snapshot.error || messageFor(snapshot);
    }
    if (cancel) {
      cancel.hidden = !!snapshot.finished;
      cancel.dataset.id = snapshot.batch_id;
    }
    if (tasks) {
      const rows = snapshot.tasks || [];
      tasks.innerHTML = rows.length
        ? rows.map(renderTask).join("")
        : '<li class="rjb-task"><span class="rjb-task__index">—</span><strong>المهام لم تُضف بعد</strong><span class="rjb-pill">انتظار</span></li>';
      const active = tasks.querySelector(".is-processing");
      if (active) {
        const top = active.offsetTop;
        const bottom = top + active.offsetHeight;
        if (top < tasks.scrollTop || bottom > tasks.scrollTop + tasks.clientHeight) {
          tasks.scrollTop = Math.max(0, top - 8);
        }
      }
    }
  }

  function messageFor(snapshot) {
    if (snapshot.status === "queued") return "العملية في الطابور، وتتنفّذ أول ما يمسكها الـ worker.";
    if (snapshot.status === "processing") return snapshot.current && snapshot.current.label
      ? "الآن: " + snapshot.current.label
      : "الـ queue يشتغل، والشريط يتحرك مع كل مهمة.";
    if (snapshot.status === "finished") return "خلصت كل المهام.";
    if (snapshot.status === "finished_with_failures") return "خلصت المعالجة، وفي مهام فشلت.";
    if (snapshot.status === "cancelled") return "تم إيقاف العملية.";
    if (snapshot.status === "failed") return snapshot.error || "توقفت العملية قبل ما تكتمل.";
    return "";
  }

  function renderTask(task, index) {
    const status = task.status || "pending";
    return (
      '<li class="rjb-task' + (status === "processing" ? " is-processing" : "") + '">' +
        '<span class="rjb-task__index">' + String(index + 1).padStart(2, "0") + "</span>" +
        "<div><strong>" + escapeHtml(task.label || "مهمة") + "</strong>" +
        (task.message ? "<small>" + escapeHtml(task.message) + "</small>" : "") +
        "</div>" +
        '<span class="rjb-pill is-' + escapeAttr(status) + '">' + escapeHtml(statusLabel(status)) + "</span>" +
      "</li>"
    );
  }

  function render() {
    renderDock();
    renderPanel();
  }

  function isStale(existing, snapshot) {
    if (!existing.updated_at || !snapshot.updated_at) {
      return false;
    }

    if (existing.updated_at > snapshot.updated_at) {
      return true;
    }

    return existing.updated_at === snapshot.updated_at
      && existing.status === snapshot.status
      && Number(existing.progress || 0) > Number(snapshot.progress || 0);
  }

  function onEvent(payload) {
    state.lastEventAt = Date.now();
    const data = typeof payload === "string" ? parseJson(payload) : payload;
    if (data && data.data && data.batch_id === undefined && typeof data.data === "string") {
      upsert(parseJson(data.data));
      return;
    }
    upsert(data);
  }

  function wsUrl(cfg) {
    const socket = socketOptions(cfg);
    const scheme = socket.forceTLS ? "wss" : "ws";
    const port = Number(socket.forceTLS ? socket.wssPort : socket.wsPort);
    const omitPort = (scheme === "wss" && port === 443) || (scheme === "ws" && port === 80);
    const key = encodeURIComponent(cfg.key || "");

    return scheme + "://" + socket.wsHost + (omitPort ? "" : ":" + port) + "/app/" + key + "?protocol=7&client=js&version=8.4.0&flash=false";
  }

  function createReverbSocket(url, onState) {
    const socket = {
      channels: new Map(),
      ws: null,
      closed: false,
      attempts: 0,
      pingTimer: null,
      reconnectTimer: null,
    };

    function send(event, data) {
      if (!socket.ws || socket.ws.readyState !== 1) {
        return;
      }
      socket.ws.send(JSON.stringify({ event: event, data: data }));
    }

    function subscribeAll() {
      socket.channels.forEach(function (_channel, name) {
        send("pusher:subscribe", { channel: name, auth: "" });
      });
    }

    function scheduleReconnect() {
      if (socket.closed) {
        return;
      }
      const delay = Math.min(15000, 1000 * Math.pow(2, socket.attempts));
      socket.attempts += 1;
      socket.reconnectTimer = window.setTimeout(open, delay);
    }

    function open() {
      if (socket.closed) {
        return;
      }

      onState("connecting");
      let established = false;
      let ws;
      try {
        ws = new WebSocket(url);
      } catch (error) {
        onState("unavailable");
        scheduleReconnect();
        return;
      }

      socket.ws = ws;
      const handshakeTimer = window.setTimeout(function () {
        if (!established) {
          try {
            ws.close();
          } catch (error) {}
        }
      }, 8000);

      ws.onmessage = function (event) {
        let message;
        try {
          message = JSON.parse(event.data);
        } catch (error) {
          return;
        }

        const data = typeof message.data === "string" ? parseJson(message.data) : message.data;

        if (message.event === "pusher:connection_established") {
          established = true;
          window.clearTimeout(handshakeTimer);
          socket.attempts = 0;
          const timeout = Number(data && data.activity_timeout) || 30;
          window.clearInterval(socket.pingTimer);
          socket.pingTimer = window.setInterval(function () {
            send("pusher:ping", {});
          }, Math.max(10000, timeout * 500));
          onState("connected");
          subscribeAll();
          return;
        }

        if (message.event === "pusher:ping") {
          send("pusher:pong", {});
          return;
        }

        const channel = socket.channels.get(message.channel);
        if (!channel || !channel[message.event]) {
          return;
        }
        channel[message.event].forEach(function (handler) {
          handler(data);
        });
      };

      ws.onclose = function () {
        window.clearTimeout(handshakeTimer);
        window.clearInterval(socket.pingTimer);
        if (socket.closed) {
          return;
        }
        onState("unavailable");
        scheduleReconnect();
      };
      ws.onerror = function () {};
    }

    socket.subscribe = function (name) {
      if (!socket.channels.has(name)) {
        socket.channels.set(name, {});
      }
      if (socket.ws && socket.ws.readyState === 1) {
        send("pusher:subscribe", { channel: name, auth: "" });
      }
      return {
        bind: function (event, handler) {
          const channel = socket.channels.get(name);
          channel[event] = channel[event] || [];
          channel[event].push(handler);
        },
      };
    };

    open();
    return socket;
  }

  function connect() {
    const cfg = config();

    if (!cfg.key || typeof WebSocket === "undefined") {
      setConnection("unavailable");
      return;
    }

    state.socket = createReverbSocket(wsUrl(cfg), setConnection);
    const channel = state.socket.subscribe(cfg.globalChannel || "job-batches");
    channel.bind(cfg.progressEvent || "progress", onEvent);
    channel.bind(cfg.finishedEvent || "finished", onEvent);
  }

  function hydrate() {
    const cfg = config();
    if (!cfg.statusBase) {
      return;
    }

    fetch(cfg.statusBase, { headers: { Accept: "application/json" } })
      .then(function (response) {
        return response.ok ? response.json() : null;
      })
      .then(function (payload) {
        const rows = payload && Array.isArray(payload.data) ? payload.data : [];
        rows.forEach(function (row) {
          upsert(row);
        });
      })
      .catch(function () {});
  }

  function startPolling() {
    const cfg = config();
    if (!cfg.statusBase || state.pollTimer) {
      return;
    }

    state.pollTimer = window.setInterval(function () {
      if (state.connection === "connected" && Date.now() - (state.lastEventAt || 0) < 4000) {
        return;
      }

      fetch(cfg.statusBase, { headers: { Accept: "application/json" } })
        .then(function (response) {
          return response.ok ? response.json() : null;
        })
        .then(function (payload) {
          const rows = payload && Array.isArray(payload.data) ? payload.data : [];
          rows.forEach(function (row) {
            upsert(row);
          });
        })
        .catch(function () {});
    }, 1500);
  }

  function cancel(batchId) {
    const cfg = config();
    const token = document.querySelector('meta[name="csrf-token"]');
    const headers = {
      Accept: "application/json",
      "Content-Type": "application/json",
    };

    if (token) {
      headers["X-CSRF-TOKEN"] = token.content;
    }

    fetch(cfg.statusBase + "/" + encodeURIComponent(batchId) + "/cancel", {
      method: "POST",
      headers: headers,
    })
      .then(function (response) {
        return response.ok ? response.json() : null;
      })
      .then(function (snapshot) {
        if (snapshot) {
          upsert(snapshot, { select: true });
        }
      })
      .catch(function () {});
  }

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function escapeAttr(value) {
    return escapeHtml(value).replace(/'/g, "&#39;");
  }

  function setText(root, selector, value) {
    const node = root.querySelector(selector);
    if (node) {
      node.textContent = String(value ?? 0);
    }
  }

  function parseJson(value) {
    try {
      return JSON.parse(value);
    } catch (error) {
      return null;
    }
  }

  document.addEventListener("click", function (event) {
    const job = event.target.closest("[data-rjb-taskbar] [data-id]");
    if (job) {
      select(job.dataset.id);
      return;
    }

    const cancelButton = event.target.closest("[data-rjb-cancel]");
    if (cancelButton && cancelButton.dataset.id) {
      cancel(cancelButton.dataset.id);
    }
  });

  window.RealtimeJobProgress = {
    upsert: upsert,
    select: select,
    connect: connect,
    connection: function () {
      return state.connection;
    },
    batches: function () {
      return snapshotList();
    },
  };

  function boot() {
    if (!document.getElementById("rjb-config")) {
      return;
    }

    render();
    connect();
    hydrate();
    startPolling();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }
})();
