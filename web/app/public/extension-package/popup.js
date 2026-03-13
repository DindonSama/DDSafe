const DEFAULT_APP_URL = "__DDSAFE_APP_URL__";
let allItems = [];
let fetchTimestamp = 0;
let timerInterval = null;
let autoRefreshTimeout = null;

function compareVersions(a, b) {
  const pa = String(a || "0").split(".").map((x) => parseInt(x, 10) || 0);
  const pb = String(b || "0").split(".").map((x) => parseInt(x, 10) || 0);
  const len = Math.max(pa.length, pb.length);
  for (let i = 0; i < len; i++) {
    const va = pa[i] || 0;
    const vb = pb[i] || 0;
    if (va > vb) return 1;
    if (va < vb) return -1;
  }
  return 0;
}

async function getAppUrl() {
  const data = await chrome.storage.sync.get({ appUrl: DEFAULT_APP_URL });
  return (data.appUrl || DEFAULT_APP_URL).replace(/\/$/, "");
}

function setStatus(msg) {
  const el = document.getElementById("status");
  el.textContent = msg;
  el.hidden = false;
}

function clearStatus() {
  const el = document.getElementById("status");
  if (el) el.hidden = true;
}

function updateTimers() {
  const elapsed = (Date.now() - fetchTimestamp) / 1000;
  document.querySelectorAll(".item").forEach((el) => {
    const remaining = parseFloat(el.dataset.remaining || 30);
    const current = Math.ceil(Math.max(0, remaining - elapsed));
    const timerEl = el.querySelector(".timer");
    if (timerEl) timerEl.textContent = current + "s";
    const codeEl = el.querySelector(".code");
    if (codeEl) codeEl.style.color = current <= 5 ? "var(--warn)" : "";
  });
}

function renderItems(items) {
  const list = document.getElementById("list");
  list.innerHTML = "";

  items.forEach((item) => {
    const div = document.createElement("article");
    div.className = "item";
    div.dataset.remaining = item.remaining || 30;
    div.dataset.period = item.period || 30;
    div.innerHTML = `
      <div class="top">
        <div class="info">
          <div class="name">${escapeHtml(item.name || "Sans nom")}</div>
          <div class="issuer">${escapeHtml(item.issuer || "-")}${item.tenant ? " - " + escapeHtml(item.tenant) : ""}</div>
        </div>
        <div class="right">
          <div class="code">${escapeHtml(item.code || "------")}</div>
          <div class="timer">-s</div>
        </div>
      </div>
    `;
    div.addEventListener("click", async () => {
      try {
        await navigator.clipboard.writeText(item.code || "");
        div.classList.add("copied");
        setTimeout(() => div.classList.remove("copied"), 1500);
      } catch {}
    });
    list.appendChild(div);
  });

  list.hidden = items.length === 0;
}

function escapeHtml(s) {
  return String(s)
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#39;");
}

async function fetchFromApi(appUrl) {
  const url = `${appUrl}/api/otp/overview`;
  const res = await fetch(url, {
    method: "GET",
    credentials: "include"
  });

  if (!res.ok) {
    return {
      ok: false,
      message: "Session invalide. Ouvre l'app une fois pour te connecter."
    };
  }

  const data = await res.json();
  const items = Array.isArray(data.items) ? data.items : [];
  if (items.length === 0) {
    return {
      ok: false,
      message: "Aucun OTP trouve pour ce compte."
    };
  }

  return {
    ok: true,
    items,
    count: items.length
  };
}

function applySearch() {
  const q = (document.getElementById("searchInput")?.value || "").trim().toLowerCase();
  const filtered = allItems.filter((item) => {
    if (q === "") return true;
    const name = String(item.name || "").toLowerCase();
    const issuer = String(item.issuer || "").toLowerCase();
    const tenant = String(item.tenant || "").toLowerCase();
    return name.includes(q) || issuer.includes(q) || tenant.includes(q);
  });
  renderItems(filtered);
  if (filtered.length === 0 && allItems.length > 0) {
    setStatus("Aucun résultat.");
  } else if (filtered.length > 0) {
    clearStatus();
  }
}

async function refresh() {
  const appUrl = await getAppUrl();
  setStatus("Lecture des OTP via API...");

  try {
    const res = await fetchFromApi(appUrl);
    if (!res.ok) {
      allItems = [];
      renderItems([]);
      setStatus(res.message);
      return;
    }

    allItems = res.items;
    fetchTimestamp = Date.now();
    applySearch();
    if (autoRefreshTimeout) clearTimeout(autoRefreshTimeout);
    const minRemaining = Math.min(...allItems.map((i) => i.remaining || 30));
    autoRefreshTimeout = setTimeout(refresh, (minRemaining + 0.5) * 1000);
    if (timerInterval) clearInterval(timerInterval);
    timerInterval = setInterval(updateTimers, 1000);
    updateTimers();
  } catch (err) {
    allItems = [];
    renderItems([]);
    setStatus("Impossible de lire l'API. Verifie l'URL dans Options.");
  }
}

function setUpdateBanner(html, hasUpdate) {
  const el = document.getElementById("updateBanner");
  el.innerHTML = html;
  el.hidden = false;
  el.className = "update-banner" + (hasUpdate ? " has-update" : "");
}

async function checkUpdate() {
  const appUrl = await getAppUrl();
  const currentVersion = chrome.runtime.getManifest().version;
  setUpdateBanner(`Verification en cours (version installee : v${escapeHtml(currentVersion)})...`, false);

  try {
    const res = await fetch(`${appUrl}/extension/update-info`, {
      method: "GET",
      credentials: "include",
      cache: "no-store"
    });

    if (!res.ok) {
      setUpdateBanner("Impossible de verifier (non connecte ou URL invalide).", false);
      return;
    }

    const data = await res.json();
    const latestVersion = String(data.latestVersion || "0.0.0");
    const cmp = compareVersions(currentVersion, latestVersion);

    if (cmp < 0) {
      setUpdateBanner(
        `&#x26A0; <strong>MAJ disponible : v${escapeHtml(latestVersion)}</strong> (installee : v${escapeHtml(currentVersion)})<br>` +
        `<button id="updateNowBtn" style="margin-top:6px;font-size:11px;padding:5px 8px;">Ouvrir la page de mise a jour</button>`,
        true
      );
      const btn = document.getElementById("updateNowBtn");
      if (btn) {
        btn.addEventListener("click", async () => {
          await chrome.tabs.create({ url: appUrl + (data.updatePageUrl || "/extension") });
        });
      }
      return;
    }

    setUpdateBanner(`\u2714 Extension a jour (v${escapeHtml(currentVersion)}).`, false);
  } catch {
    setUpdateBanner("Erreur pendant la verification de mise a jour.", false);
  }
}

document.getElementById("refreshBtn").addEventListener("click", refresh);
document.getElementById("checkUpdateBtn").addEventListener("click", checkUpdate);
document.getElementById("searchBtn").addEventListener("click", applySearch);
document.getElementById("searchInput").addEventListener("input", applySearch);
document.getElementById("searchInput").addEventListener("keydown", (e) => {
  if (e.key === "Enter") {
    e.preventDefault();
    applySearch();
  }
});

document.getElementById("openOptionsBtn").addEventListener("click", () => {
  if (chrome.runtime.openOptionsPage) {
    chrome.runtime.openOptionsPage();
  }
});

document.getElementById("openAppBtn").addEventListener("click", async () => {
  const appUrl = await getAppUrl();
  await chrome.tabs.create({ url: appUrl + "/otp" });
});

const searchInputEl = document.getElementById("searchInput");
if (searchInputEl) {
  setTimeout(() => {
    searchInputEl.focus();
    searchInputEl.select();
  }, 0);
}

refresh();
