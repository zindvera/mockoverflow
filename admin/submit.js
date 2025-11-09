const express = require('express');
const fs = require('fs');
const path = require('path');
const { exec } = require('child_process');

const app = express();
app.use(express.json());

const repoPath = path.resolve(__dirname); // assuming submit.js in root of repo
const clientsJsonPath = path.join(repoPath, 'clients.json');
const clientsFolder = path.join(repoPath, 'clients');

if (!fs.existsSync(clientsFolder)) {
  fs.mkdirSync(clientsFolder);
}

// Helper to read JSON file safely
function readJson(filePath) {
  if (!fs.existsSync(filePath)) return null;
  const data = fs.readFileSync(filePath, 'utf8');
  return JSON.parse(data);
}

// Helper to write JSON file pretty
function writeJson(filePath, data) {
  fs.writeFileSync(filePath, JSON.stringify(data, null, 2), 'utf8');
}

// Function to run git commands
function runGitCommands(commitMessage, callback) {
  const commands = [
    'git add .',
    `git commit -m "${commitMessage}"`,
    'git push',
  ].join(' && ');
  exec(commands, { cwd: repoPath }, (err, stdout, stderr) => {
    if (err) {
      console.error('Git error:', stderr);
      callback(err);
      return;
    }
    callback(null, stdout);
  });
}

app.post('/submit', (req, res) => {
  const { clientName, whatsappNumber, productData } = req.body;
  if (!whatsappNumber) {
    res.status(400).json({ error: 'WhatsApp number is required' });
    return;
  }

  // Step 1: Update clients.json
  let clients = readJson(clientsJsonPath) || [];

  // Check if client exists by whatsappNumber
  let client = clients.find(c => c.whatsapp === whatsappNumber);
  if (!client) {
    // Create new client with new id
    const newId = clients.length ? Math.max(...clients.map(c => c.id)) + 1 : 1;
    client = { id: newId, name: clientName || 'No Name', whatsapp: whatsappNumber };
    clients.push(client);
    writeJson(clientsJsonPath, clients);
  }

  // Step 2: Update client product JSON
  const clientFilePath = path.join(clientsFolder, `${client.id}.json`);
  let clientProducts = readJson(clientFilePath) || [];

  // Add productData entry to clientProducts
  if (productData && typeof productData === 'object') {
    clientProducts.push(productData);
    writeJson(clientFilePath, clientProducts);
  }

  // Step 3: Commit and push changes to GitHub
  runGitCommands(`Update client data for client id ${client.id}`, (err, stdout) => {
    if (err) {
      res.status(500).json({ error: 'Git operation failed' });
      return;
    }
    res.json({ message: 'Client data updated successfully', clientId: client.id });
  });
});

const PORT = 3000;
app.listen(PORT, () => {
  console.log(`Server listening on http://localhost:${PORT}`);
});
