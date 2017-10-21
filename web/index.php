<?php

$teamId = $_POST['team_id'];

$allowedProjects = include('../projects.php');

if (
  $_POST['team_domain'] == 'afup'
  && $_POST['channel_id'] == getenv('SLACK_ALLOWED_CHANNEL_ID')
  && $_POST['token'] == getenv('SLACK_TOKEN')
) {
  $project = trim($_POST['text']);
  if (isset($allowedProjects[$project])) {
    $triggersDir = __DIR__ . '/../triggers';
    touch($triggersDir . '/' . $project);
    $responseText = sprintf("Le déploiement du projet %s va commencer dans moins d'une minute", $project);
  } else {
    $responseText = sprintf("Projet inconnu %s (projects possibles : %s)", $projet, implode(',', array_keys($allowedProjects)));
  }
} else {
  $responseText = "Vous n'êtes pas autorisé à effectuer un déploiement";
}

$response = [
  'response_type' => 'in_channel',
  'text' => $responseText,
];

$jsonResponse = json_encode($response);

header("Content-Type: application/json");
echo $jsonResponse;
