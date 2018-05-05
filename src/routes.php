<?php
if (!function_exists('hash_equals')) {
    defined('USE_MB_STRING') or define('USE_MB_STRING', function_exists('mb_strlen'));
    function hash_equals($knownString, $userString)
    {
        $strlen = function ($string) {
            if (USE_MB_STRING) {
                return mb_strlen($string, '8bit');
            }
            return strlen($string);
        };
        // Compare string lengths
        if (($length = $strlen($knownString)) !== $strlen($userString)) {
            return false;
        }
        $diff = 0;
        // Calculate differences
        for ($i = 0; $i < $length; $i++) {
            $diff |= ord($knownString[$i]) ^ ord($userString[$i]);
        }
        return $diff === 0;
    }
}

use Slim\Http\Request;
use Slim\Http\Response;

use LINE\LINEBot\MessageBuilder\TextMessageBuilder;
use LINE\LINEBot\MessageBuilder\ImageMessageBuilder;
use LINE\LINEBot\Constant\HTTPHeader;
use LINE\LINEBot\Event\MessageEvent;
use LINE\LINEBot\Event\MessageEvent\TextMessage;
use LINE\LINEBot\Exception\InvalidEventRequestException;
use LINE\LINEBot\Exception\InvalidSignatureException;



// Routes

$app->get('/push/[{message}]', function (Request $request, Response $response, array $args) {
    $httpClient = new \LINE\LINEBot\HTTPClient\CurlHTTPClient(getenv('CHANNEL_ACCESS_TOKEN'));
    $bot = new \LINE\LINEBot($httpClient, ['channelSecret' => getenv('CHANNEL_SECRET')]);
    foreach ($args as $arg){
        $this->logger->info("$arg: " . $arg);
    }
    $this->logger->info("count_arg: " . count($args));
    $args["name"] = "pushed";
    $user_id = getenv('USER_ID');
    $text = $args['message'];
    $textMessageBuilder = new TextMessageBuilder($text);
    $bot->pushMessage($user_id, $textMessageBuilder);
    return $this->renderer->render($response, 'index.phtml', $args);
});

$app->post('/callback', function (\Slim\Http\Request $req, \Slim\Http\Response $res) {
    $httpClient = new \LINE\LINEBot\HTTPClient\CurlHTTPClient(getenv('CHANNEL_ACCESS_TOKEN'));
    $bot = new \LINE\LINEBot($httpClient, ['channelSecret' => getenv('CHANNEL_SECRET')]);
    $logger = $this->logger;

    $originalContentUrl = "";
    $previewImageUrl = "";
    $imageMessageBuilder = new ImageMessageBuilder($originalContentUrl, $previewImageUrl);
    $user_id = getenv('USER_ID');
    $bot->pushMessage($user_id, $imageMessageBuilder);
    $res->write('OK');
    return $res;
});

$app->get('/[{name}]', function (Request $request, Response $response, array $args) {
    // Sample log message
    $this->logger->info("Slim-Skeleton '/' route");
    foreach ($args as $arg){
        $this->logger->info("$arg: " . $arg);
    }
    $this->logger->info("count_arg: " . $args);

    // Render index view
    return $this->renderer->render($response, 'index.phtml', $args);
});

$app->post('/callback', function (\Slim\Http\Request $req, \Slim\Http\Response $res) {
    /** @var \LINE\LINEBot $bot */
    $httpClient = new \LINE\LINEBot\HTTPClient\CurlHTTPClient(getenv('CHANNEL_ACCESS_TOKEN'));
    $bot = new \LINE\LINEBot($httpClient, ['channelSecret' => getenv('CHANNEL_SECRET')]);
    /** @var \Monolog\Logger $logger */
    $logger = $this->logger;
    $signature = $req->getHeader(HTTPHeader::LINE_SIGNATURE);
    if (empty($signature)) {
        return $res->withStatus(400, 'Bad Request');
    }
    // Check request with signature and parse request
    try {
        $events = $bot->parseEventRequest($req->getBody(), $signature[0]);
    } catch (InvalidSignatureException $e) {
        return $res->withStatus(400, 'Invalid signature');
    } catch (InvalidEventRequestException $e) {
        return $res->withStatus(400, "Invalid event request");
    }
    foreach ($events as $event) {
        if (!($event instanceof MessageEvent)) {
            $logger->info('Non message event has come');
            continue;
        }
        if (!($event instanceof TextMessage)) {
            $logger->info('Non text message has come');
            continue;
        }
        $replyText = $event->getText();
        $logger->info('Reply text: ' . $replyText);
        $resp = $bot->replyText($event->getReplyToken(), $replyText);
        $logger->info($resp->getHTTPStatus() . ': ' . $resp->getRawBody());
    }
    $res->write('OK');
    return $res;
});



