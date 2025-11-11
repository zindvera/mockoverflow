<?php
function gitCommitAndPushJsonFile($repoDir, $jsonFilePath, $commitMessage) {
    $fullJsonPath = rtrim($repoDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $jsonFilePath;
    $safeMessage = escapeshellarg($commitMessage);
    
    // Commands:
    $commands = [
        "cd " . escapeshellarg($repoDir),
        // Ensure remote uses SSH
        "git remote set-url origin git@github.com:zindvera/mockoverflow.git",
        // Stage only the specific JSON file that was updated
        "git add " . escapeshellarg($jsonFilePath),
        // Commit with message
        "git commit -m $safeMessage",
        // Push to master branch
        "git push origin master"
    ];
    
    $fullCommand = implode(' && ', $commands);
    exec($fullCommand . ' 2>&1', $output, $returnVar);
    
    if ($returnVar !== 0) {
        error_log("Git push failed: " . implode("\n", $output));
        return false;
    }
    return true;
}
