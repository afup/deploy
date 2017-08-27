<?php

function slackNotification($text, array $links = []) {
  $payload = [
    'channel' => '#test_depoy3',
    'text' => $text,
  ];

  $slackToken = getenv('DEPLOY_SLACK_TOKEN');

  foreach ($links as $title => $href) {
    $payload['attachments'][] = [
      "title_link" => $href,
      "text" => $title,
    ];
  }

  $url = "https://hooks.slack.com/services/";

  $jsonPayload = json_encode($payload);
  $curl = curl_init($url . $slackToken);
  curl_setopt_array($curl, [
      CURLOPT_RETURNTRANSFER => 1,
      CURLOPT_POSTFIELDS => http_build_query(['payload' => $jsonPayload]),
  ]);

  $result = curl_exec($curl);

  if ($result != 'ok') {
    throw new \RuntimeException('Notification slack impossible : ' . var_export($result, true));
  }
}

$projects = [
  'web' => ['Voir event.afup.org' => 'https://event.afup.org'],
];

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

$playbook = escapeshellarg(__DIR__ . '/playbooks/' . $project . '.yml');

$command = 'ANSIBLE_LOCAL_TEMP=/tmp/ansible_local_tmp_deploy ANSIBLE_REMOTE_TEMP=/tmp/ansible_remote_tmp_deploy /usr/local/bin/ansible-playbook  -i "localhost," -c local -vvv ' . $playbook;


slackNotification(sprintf('Le déploiement du projet %s a débuté', $project));

$output = [];
$return = 0;
exec($command, $output, $return);
$outputStr = implode(PHP_EOL, $output);

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

slackNotification(sprintf('Le projet %s a été mis à jour', $project), isset($projects[$project]) ? $projects[$project] : []);

