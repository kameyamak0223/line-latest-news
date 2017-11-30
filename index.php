<?php

require_once __DIR__ . '/magpierss/rss_fetch.inc';
require_once __DIR__ . '/vendor/autoload.php';

$httpClient = new \LINE\LINEBot\HTTPClient\CurlHTTPClient(getenv('CHANNEL_ACCESS_TOKEN'));
$bot = new \LINE\LINEBot($httpClient, ['channelSecret' => getenv('CHANNEL_SECRET')]);

$signature = $_SERVER["HTTP_" . \LINE\LINEBot\Constant\HTTPHeader::LINE_SIGNATURE];

try{
    $events = $bot->parseEventRequest(file_get_contents('php://input'), $signature);
}catch(\LINE\LINEBot\Exception\InvalidSignatureException $e){
    error_log("parseEventRequest failed. InvalidSignatureExecption => " . var_export($e, true));
}catch (\LINE\LINEBot\Exception\UnknowenEventTypeException $e){
    error_log("parseEventRequest failed. UnknownEventTypeException => " . var_export($e, true));
}catch (\LINE\LINEBot\Exception\UnknowenMessageTypeException $e){
    error_log("parseEventRequest failed UnknownMessageTypeException => " . var_export($e, true));
}catch(\LINE\LINEBot\Exception\InvalidEventRequestException $e){
    error_log("parseEventRequest failed. InvalidEventRequestException => " . var_export($e, true));
}

foreach($events as $event){
    if(!($event instanceof \LINE\LINEBot\Event\MessageEvent)){
        error_log('Non message event has come');
        continue;
    }
    
    if(!($event instanceof \LINE\LINEBot\Event\MessageEvent\TextMessage)){
        error_log('Non text message has come');
        continue;
    }
    
    $url = getURL_GoogleNewsSearch($event->getText(), 10);
    $rss = fetch_rss($url);

    $items = array();
    analyseGoogleNews($rss, $items);
    foreach($items as $item){
        replyTextMessage($bot, $event->getReplyToken(), item['url']);
    }
}

$url = getURL_GoogleNewsSearch('水素', 10);
$rss = fetch_rss($url);

$items = array();
analyseGoogleNews($rss, $items);

var_dump($items);
/**
 * Googleニュース検索URLを生成する(RSS2.0出力)
 * @param string $query 検索キー(INTERNAL_ENCODING)
 * @param int $nums 検索件数
 * @return string 検索URL
 */
function getURL_GoogleNewsSearch($query, $nums){
    $query = urlencode($query);
    $url = "https://news.google.com/news?ned=us&ie=UTF-8&oe=UTF-8&output=rss&hl=ja&num={$nums}&q={$query}";
    
    return $url;
}


/**
 * Googleニュースを検索し、解析結果を配列に格納
 * @param object $rss MagpieRSSの出力
 * @param array $items 解析結果を格納する配列
 * @return int 解析結果
 */
function analyseGoogleNews($rss, &$items){
    $key = 0;
    foreach($rss->items as $val){
        //タイトルと掲載紙を分離
        static $pat = "/(.+)\-([^\-]+)/ui";
        if(preg_match($pat, $val['title'], $arr)){
            $items[$key]['title'] = $arr[1];
            $items[$key]['media'] = $arr[2];
        }else{
            $items[$key]['title'] = $val['title'];
            $items[$key]['media'] = '';
        }
        $items[$key]['url'] = $val['title'];
        $items[$key]['ts'] = strtotime($val['pubdate']);
        $key++;
    }
    
    return $key;
}

/**
 * Line入力に対してテキストを返信する
 * @param type $bot
 * @param type $replyToken
 * @param type $text
 */
function replyTextMessage($bot, $replyToken, $text){
    $response = $bot->replyMessage($replyToken, new \LINE\LINEBot\MessageBuilder\TextMessageBuilder($text));
    if(!$response->isSucceeded()){
        error_log('Failed!' . $response->getHTTPStatus . ' ' . $response->getRawBody());
    }
}