<?php

require_once __DIR__ . '/lib.php';

$projects = include('projects.php');

if (!isset($argv[1])) {
  throw new \InvalidArgumentException('Undefined project name');
}

$project = $argv[1];

$triggerFile = __DIR__ . '/triggers/' . $project;

if (!is_file($triggerFile)) {
  exit(0);
}

if (!unlink($triggerFile)) {
  throw new \RuntimeException('Erreur suppressison du fichier de trigger ' . $lockFile);
}

$lockFile = __DIR__ . '/locks/' . $project;

if (is_file($lockFile)) {
  exit(0);
}

if (!touch($lockFile)) {
  throw new \RuntimeException('Erreur écriture du fichier de lock ' . $lockFile);
}

$channelName = "#outils-notifications";

$playbook = escapeshellarg(__DIR__ . '/playbooks/' . $project . '.yml');

$command = 'ANSIBLE_LOCAL_TEMP=/tmp/ansible_local_tmp_deploy ANSIBLE_REMOTE_TEMP=/tmp/ansible_remote_tmp_deploy ANSIBLE_CALLBACK_WHITELIST=profile_tasks /usr/local/bin/ansible-playbook  -i "localhost," -c local ' . $playbook . ' | grep --line-buffered -v -P "changed:|ok:|\(\d{1}:\d{2}:\d{2}.\d{3}\)|==============" ';

$slack = new Slack(getenv('DEPLOY_API_KEY'), "Déploiement", 'https://avatars2.githubusercontent.com/u/1090307?s=200&v=4');
$deploySlack = new DeploySlack($slack, $channelName);

$slack->postMessage($channelName, sprintf('Le déploiement du projet %s a débuté', $project));

$outputStr = $deploySlack->executeAndSendToSlack($slack, $command);

$logFile = __DIR__ . '/logs/deploy_' . $project . '_' . date('Y-m-d_H-i-s') . '_' . getmypid() . '.log';
if (!file_put_contents($logFile, $outputStr)) {
  throw new \RuntimeException('Erreur écriture log ' . $logFile);
}

if ($return) {
  throw new RuntimeException("Erreur au lancement d'ansible (' . $return . ') : " . $outputStr);
}

if (!unlink($lockFile)) {
  throw new \RuntimeException('Erreur suppression du fichier de lock ' . $lockFile);
}

$slack->postMessage($channelName, sprintf('Le projet %s a été mis à jour', $project), isset($projects[$project]) ? $projects[$project] : []);
