const { app, BrowserWindow } = require('electron');
const createWindow = () => {
  const win = new BrowserWindow({ width: 1100, height: 780, webPreferences: { nodeIntegration: false, contextIsolation: true, sandbox: true } });
  win.loadURL('http://127.0.0.1:8000');
  win.webContents.setWindowOpenHandler(() => ({ action: 'deny' }));
  win.webContents.on('will-navigate', (event, url) => { if (!url.startsWith('http://127.0.0.1:8000/')) event.preventDefault(); });
};
app.whenReady().then(createWindow);
app.on('window-all-closed', () => app.quit());
