const DEFAULT_APP_URL = "__DDSAFE_APP_URL__";

async function load() {
  const data = await chrome.storage.sync.get({ appUrl: DEFAULT_APP_URL });
  document.getElementById("appUrl").value = data.appUrl || DEFAULT_APP_URL;
}

async function save() {
  let value = document.getElementById("appUrl").value.trim();
  if (!value) value = DEFAULT_APP_URL;
  value = value.replace(/\/$/, "");

  await chrome.storage.sync.set({ appUrl: value });
  document.getElementById("msg").textContent = "Options enregistrees.";
}

document.getElementById("saveBtn").addEventListener("click", save);
load();
