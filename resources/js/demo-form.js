(function () {
  const form = document.getElementById("rjb-start-form");

  if (!form) {
    return;
  }

  const count = form.querySelector('[name="count"]');
  const pace = form.querySelector('[name="pace_ms"]');
  const countOut = form.querySelector("[data-count-out]");
  const paceOut = form.querySelector("[data-pace-out]");
  const note = form.querySelector("[data-form-note]");
  const button = form.querySelector('[type="submit"]');

  function sync() {
    if (count && countOut) {
      countOut.textContent = count.value;
    }
    if (pace && paceOut) {
      paceOut.textContent = (Number(pace.value) / 1000).toFixed(1) + " ث";
    }
  }

  count?.addEventListener("input", sync);
  pace?.addEventListener("input", sync);
  sync();

  form.addEventListener("submit", async function (event) {
    event.preventDefault();
    if (button) {
      button.disabled = true;
    }
    if (note) {
      note.textContent = "جاري إدراج العملية في الطابور…";
    }

    try {
      const token = document.querySelector('meta[name="csrf-token"]')?.content;
      const headers = {
        "Content-Type": "application/json",
        Accept: "application/json",
      };

      if (token) {
        headers["X-CSRF-TOKEN"] = token;
      }

      const response = await fetch(form.action || "/realtime-job-batches/demo", {
        method: "POST",
        headers: headers,
        body: JSON.stringify({
          name: form.name.value,
          count: Number(form.count.value),
          pace_ms: Number(form.pace_ms.value),
          simulate_failure: form.simulate_failure.checked,
        }),
      });
      const payload = await response.json();

      if (!response.ok) {
        const validation = payload.errors ? Object.values(payload.errors)[0] : null;
        throw new Error((validation && validation[0]) || payload.message || "تعذّر بدء العملية");
      }

      window.RealtimeJobProgress?.upsert(payload.progress, { select: true });
      if (note) {
        note.textContent = "انضافت للطابور. الشريط يتحدث مباشرة.";
      }
    } catch (error) {
      if (note) {
        note.textContent = error.message;
      }
    } finally {
      if (button) {
        button.disabled = false;
      }
    }
  });
})();
