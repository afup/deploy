<?php

class DeploySlack {

  protected $slack;
  protected $channelName;

  public function __construct(Slack $slack, $channelName) {
    $this->slack = $slack;
    $this->channelName = $channelName;
  }

  private function execute($cmd, $stdoutCallback = null, $stderrCallback = null, $bufferLength = 128) {
    $descriptorspec = array(
      1 => array('pipe', 'w'), // stdout
      2 => array('pipe', 'w'), // stderr
    );
    $process = proc_open($cmd, $descriptorspec, $pipes);
    if (!is_resource($process))
    {
      throw new RuntimeException('Unable to execute the command.');
    }
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $output = '';
    $err = '';
    while (!feof($pipes[1]) || !feof($pipes[2]))
    {
      foreach ($pipes as $key => $pipe)
      {
        if (!$line = fread($pipe, $bufferLength))
        {
          continue;
        }
        if (1 == $key)
        {
          // stdout
          $output .= $line;
          if ($stdoutCallback)
          {
            call_user_func($stdoutCallback, $line);
          }
        }
        else
        {
          // stderr
          $err .= $line;
          if ($stderrCallback)
          {
            call_user_func($stderrCallback, $line);
          }
        }
      }
      usleep(100000);
    }
    fclose($pipes[1]);
    fclose($pipes[2]);
    if (($return = proc_close($process)) > 0)
    {
      throw new RuntimeException('Problem executing command.', $return);
    }
    return array($output, $err);
  }

  public function executeAndSendToSlack(Slack $slack, $command) {
    $message = "Log du déploiement :";
    $resultArray = $this->slack->postMessage($this->channelName, $message);

    $logContent = '';
    $callbackStdout = function($line) use(&$logContent, $resultArray, $message) {
      $logContent .= $line;
      $lines = explode("\n", $logContent);

      $nbDisplayedLines = 5;
      $displayedLines = array_fill(0, $nbDisplayedLines, " ");
      $lastLines = array_slice($lines, count($lines) - $nbDisplayedLines + 1, count($lines) - 1);
      $displayedLines = $lastLines + $displayedLines;

      $this->slack->updateMessage($resultArray['channel'], $resultArray['ts'], $message, implode($displayedLines, "\n"));
    };

    $this->execute($command, $callbackStdout);

    $this->slack->updateMessage($resultArray['channel'], $resultArray['ts'], $message, $logContent);

    return $logContent;
  }
}

class Slack {

  protected $token;
  protected $username;
  protected $iconUrl;

  public function __construct($token, $username, $iconUrl) {
    $this->token = $token;
    $this->username = $username;
    $this->iconUrl = $iconUrl;
  }

  public function postMessage($channelName, $message, array $links = []) {
    $parameters = [
      "token" => $this->token,
      "channel" => $channelName,
      "text" => $message,
      "icon_url" => $this->iconUrl,
      'username' => $this->username,
    ];

    $attachments = [];
    foreach ($links as $title => $href) {
      $attachments[] = [
        "title_link" => $href,
        "text" => $title,
      ];
    }

    if (count($attachments)) {
      $parameters['attachments'] = json_encode($attachments);
    }

    $url = "https://slack.com/api/chat.postMessage";
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_POSTFIELDS => http_build_query($parameters),
    ]);

    $result = curl_exec($curl);

    return json_decode($result, true);
  }

  public function updateMessage($channelId, $ts = null, $message, $text = "") {
    $url = "https://slack.com/api/chat.update";

    $parameters = [
      "token" => $this->token,
      "channel" => $channelId,
      "text" => $message,
      "icon_url" => $this->iconUrl,
      "username" => $this->username,
      "attachments" => json_encode([
        [
          "text" => $text,
        ]
      ]),
      "ts" => $ts,
    ];

    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_POSTFIELDS => http_build_query($parameters),
    ]);

    $result = curl_exec($curl);

    return json_decode($result, true);
  }
}

