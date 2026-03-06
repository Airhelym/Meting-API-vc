<?php
// 生产环境：关闭错误显示，防止路径泄露
ini_set('display_errors', 'Off');
error_reporting(0);

// 可选：将错误记录到日志
// ini_set('log_errors', 'On');
// ini_set('error_log', '/path/to/your/error.log');

// 允许跨站
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

// 检查参数是否缺失
if (!isset($_GET['type']) || !isset($_GET['id'])) {
    // 设置 Content-Type 为 HTML
    header('Content-Type: text/html; charset=utf-8');
    http_response_code(400);
    // 不再尝试读取本地文件，避免路径泄露
    echo "<!DOCTYPE html>
<html>
<head><meta charset='utf-8'><title>400 Bad Request</title></head>
<body>
<h1>400 Bad Request</h1>
<p>Missing required parameters: <code>type</code> and <code>id</code>.</p>
</body>
</html>";
    exit;
}

// 设置API路径
define('API_URI', api_uri());
// 设置中文歌词
define('TLYRIC', true);
// 设置歌单文件缓存及时间
define('CACHE', false);
define('CACHE_TIME', 86400);
// 设置短期缓存-需要安装apcu
define('APCU_CACHE', false);
// 设置AUTH密钥-更改'meting-secret'
define('AUTH', false);
define('AUTH_SECRET', 'meting-secret');

// 检查必需的参数
// 官方示例中，搜索时 'id' 是关键词，其他情况是ID
// if (!isset($_GET['type']) || !isset($_GET['id'])) {
//     // 如果参数不全，可以返回一个简单的帮助页面或错误
//     http_response_code(400);
//     echo json_encode(['error' => 'Missing required parameters: type and id']);
//     exit;
// }

$server = isset($_GET['server']) ? $_GET['server'] : 'netease'; // 默认网易云
$type = $_GET['type'];
$id = $_GET['id'];

// 验证 AUTH（如果开启）
if (AUTH) {
    $auth = isset($_GET['auth']) ? $_GET['auth'] : '';
    // 对于需要鉴权的资源类型（url, pic, lrc），检查auth参数
    if (in_array($type, ['url', 'pic', 'lrc'])) {
        if ($auth == '' || $auth != auth($server . $type . $id)) {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden']);
            exit;
        }
    }
}

// 包含 Meting 核心库
include __DIR__ . '/src/meting.php'; // 请根据实际路径调整
use Metowolf\Meting;

// 创建 Meting 实例
$api = new Meting($server);
$api->format(true);

// 修复腾讯 Cookie 缺少 uin 的问题
if ($server === 'tencent') {
    $currentCookie = $api->header['Cookie'] ?? '';
    if (!preg_match('/uin=\d+/', $currentCookie)) {
        // 注入一个 guest uin
        $api->header['Cookie'] = 'uin=1234567890; ' . $currentCookie;
    }
}
// 处理不同的 type
switch ($type) {
    case 'song':
        // 获取单曲信息
        $data = $api->song($id);
        if ($data == '[]' || $data == 'null') {
            return_error('Unknown song ID');
        }
        $song = json_decode($data)[0];
        // 构造符合官方示例返回格式的响应
        $response = [
            'name'   => $song->name,
            'artist' => implode('/', $song->artist),
            'url'    => API_URI . '?server=' . $server . '&type=url&id=' . $song->url_id . (AUTH ? '&auth=' . auth($server . 'url' . $song->url_id) : ''),
            'pic'    => API_URI . '?server=' . $server . '&type=pic&id=' . $song->pic_id . (AUTH ? '&auth=' . auth($server . 'pic' . $song->pic_id) : ''),
            'lrc'    => API_URI . '?server=' . $server . '&type=lrc&id=' . $song->lyric_id . (AUTH ? '&auth=' . auth($server . 'lrc' . $song->lyric_id) : '')
        ];
        return_json([$response]);
        break;

    case 'playlist':
        // 缓存处理
        if (CACHE) {
            $file_path = __DIR__ . '/cache/playlist/' . $server . '_' . $id . '.json';
            if (file_exists($file_path) && (time() - filemtime($file_path) < CACHE_TIME)) {
                echo file_get_contents($file_path);
                exit;
            }
        }

        // 获取歌单
        $data = $api->playlist($id);
        if ($data == '[]' || $data == 'null') {
            return_error('Unknown playlist ID');
        }
        $songs = json_decode($data);
        $playlist = [];
        foreach ($songs as $song) {
            $playlist[] = [
                'name'   => $song->name,
                'artist' => implode('/', $song->artist),
                'url'    => API_URI . '?server=' . $song->source . '&type=url&id=' . $song->url_id . (AUTH ? '&auth=' . auth($song->source . 'url' . $song->url_id) : ''),
                'pic'    => API_URI . '?server=' . $song->source . '&type=pic&id=' . $song->pic_id . (AUTH ? '&auth=' . auth($song->source . 'pic' . $song->pic_id) : ''),
                'lrc'    => API_URI . '?server=' . $song->source . '&type=lrc&id=' . $song->lyric_id . (AUTH ? '&auth=' . auth($song->source . 'lrc' . $song->lyric_id) : '')
            ];
        }

        // 缓存歌单
        if (CACHE) {
            $cache_dir = dirname($file_path);
            if (!is_dir($cache_dir)) mkdir($cache_dir, 0755, true);
            file_put_contents($file_path, $playlist_json);
        }
        return_json($playlist);
        break;

    case 'search':
        // 获取搜索关键词
        $keyword = $id; // 在官方示例中，搜索的 'id' 参数是关键词
        // 可以通过其他参数（如 page, limit）来控制搜索结果
        $option = [];
        if (isset($_GET['page'])) $option['page'] = (int)$_GET['page'];
        if (isset($_GET['limit'])) $option['limit'] = (int)$_GET['limit'];
        // 执行搜索
        $data = $api->search($keyword, $option);
        if ($data == '[]' || $data == 'null') {
            return_error('No search results');
        }
        $songs = json_decode($data);
        $results = [];
        foreach ($songs as $song) {
            $results[] = [
                'name'   => $song->name,
                'artist' => implode('/', $song->artist),
                'url'    => API_URI . '?server=' . $song->source . '&type=url&id=' . $song->url_id . (AUTH ? '&auth=' . auth($song->source . 'url' . $song->url_id) : ''),
                'pic'    => API_URI . '?server=' . $song->source . '&type=pic&id=' . $song->pic_id . (AUTH ? '&auth=' . auth($song->source . 'pic' . $song->pic_id) : ''),
                'lrc'    => API_URI . '?server=' . $song->source . '&type=lrc&id=' . $song->lyric_id . (AUTH ? '&auth=' . auth($song->source . 'lrc' . $song->lyric_id) : '')
            ];
        }
        return_json($results);
        break;

    case 'album':
        // 获取专辑
        $data = $api->album($id);
        if ($data == '[]' || $data == 'null') {
            return_error('Unknown album ID');
        }
        $songs = json_decode($data);
        $album_songs = [];
        foreach ($songs as $song) {
            $album_songs[] = [
                'name'   => $song->name,
                'artist' => implode('/', $song->artist),
                'url'    => API_URI . '?server=' . $song->source . '&type=url&id=' . $song->url_id . (AUTH ? '&auth=' . auth($song->source . 'url' . $song->url_id) : ''),
                'pic'    => API_URI . '?server=' . $song->source . '&type=pic&id=' . $song->pic_id . (AUTH ? '&auth=' . auth($song->source . 'pic' . $song->pic_id) : ''),
                'lrc'    => API_URI . '?server=' . $song->source . '&type=lrc&id=' . $song->lyric_id . (AUTH ? '&auth=' . auth($song->source . 'lrc' . $song->lyric_id) : '')
            ];
        }
        return_json($album_songs);
        break;

    case 'artist':
        // 获取艺术家热门歌曲
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
        $data = $api->artist($id, $limit);
        if ($data == '[]' || $data == 'null') {
            return_error('Unknown artist ID or no songs');
        }
        $songs = json_decode($data);
        $artist_songs = [];
        foreach ($songs as $song) {
            $artist_songs[] = [
                'name'   => $song->name,
                'artist' => implode('/', $song->artist),
                'url'    => API_URI . '?server=' . $song->source . '&type=url&id=' . $song->url_id . (AUTH ? '&auth=' . auth($song->source . 'url' . $song->url_id) : ''),
                'pic'    => API_URI . '?server=' . $song->source . '&type=pic&id=' . $song->pic_id . (AUTH ? '&auth=' . auth($song->source . 'pic' . $song->pic_id) : ''),
                'lrc'    => API_URI . '?server=' . $song->source . '&type=lrc&id=' . $song->lyric_id . (AUTH ? '&auth=' . auth($song->source . 'lrc' . $song->lyric_id) : '')
            ];
        }
        return_json($artist_songs);
        break;

    case 'url':
        // 获取音频直链
        // 短期缓存处理
        if (APCU_CACHE) {
            $apcu_key = $server . '_url_' . $id;
            if (apcu_exists($apcu_key)) {
                $url_data = apcu_fetch($apcu_key);
                return_redirect($url_data->url);
            }
        }

        // 获取比特率 (br)
        $br = isset($_GET['br']) ? (int)$_GET['br'] : 320;

        $m_url = json_decode($api->url($id, $br))->url;
        if ($m_url == '') {
            return_error('Failed to get audio URL');
        }

        // 特殊处理：网易云音乐使用 HTTPS
        if ($server == 'netease') {
            $m_url = str_replace('http://', 'https://', $m_url);
        }

        if ($server == 'tencent') {
            $m_url = str_replace('http://', 'https://', $m_url);
        }

        // 缓存 URL
        if (APCU_CACHE) {
            apcu_store($apcu_key, ['url' => $m_url], 600); // 缓存10分钟
        }
        return_redirect($m_url);
        break;

    case 'pic':
        // 获取图片直链
        if (APCU_CACHE) {
            $apcu_key = $server . '_pic_' . $id;
            if (apcu_exists($apcu_key)) {
                $pic_data = apcu_fetch($apcu_key);
                return_redirect($pic_data->url);
            }
        }

        $size = isset($_GET['size']) ? (int)$_GET['size'] : 90;
        $pic_url = json_decode($api->pic($id, $size))->url;
        if (APCU_CACHE) {
            apcu_store($apcu_key, ['url' => $pic_url], 36000); // 缓存10小时
        }
        return_redirect($pic_url);
        break;

    case 'lrc':
    case 'lyric':
        // 获取歌词
        if (APCU_CACHE) {
            $apcu_key = $server . '_lrc_' . $id;
            if (apcu_exists($apcu_key)) {
                $lrc_text = apcu_fetch($apcu_key);
                return_text($lrc_text);
            }
        }

        $lrc_data = json_decode($api->lyric($id));
        if ($lrc_data->lyric == '') {
            $lrc = '[00:00.00]这似乎是一首纯音乐呢，请尽情欣赏它吧！';
        } else if ($lrc_data->tlyric == '' || !TLYRIC) {
            $lrc = $lrc_data->lyric;
        } else {
            // 合并中译歌词
            $lrc_arr = explode("\n", $lrc_data->lyric);
            $lrc_cn_arr = explode("\n", $lrc_data->tlyric);
            $lrc_cn_map = [];
            foreach ($lrc_cn_arr as $line) {
                if (trim($line) == '') continue;
                $parts = explode(']', $line, 2);
                if (count($parts) == 2) {
                    $key = $parts[0] . ']';
                    $lrc_cn_map[$key] = trim($parts[1]);
                }
            }
            foreach ($lrc_arr as $i => $line) {
                if (trim($line) == '') continue;
                $parts = explode(']', $line, 2);
                if (count($parts) == 2) {
                    $key = $parts[0] . ']';
                    if (isset($lrc_cn_map[$key]) && $lrc_cn_map[$key] != '//') {
                        $lrc_arr[$i] .= ' (' . $lrc_cn_map[$key] . ')';
                    }
                }
            }
            $lrc = implode("\n", $lrc_arr);
        }

        if (APCU_CACHE) {
            apcu_store($apcu_key, $lrc, 36000);
        }
        return_text($lrc);
        break;

    default:
        return_error('Unknown type: ' . $type);
        break;
}

// --- 辅助函数 ---
function api_uri()
{
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https://' : 'http://';
    $request_uri = strtok($_SERVER['REQUEST_URI'], '?');
    
    // 移除文件名部分
    if (strpos($request_uri, 'api.php') !== false) {
        $request_uri = str_replace('api.php', 'api', $request_uri);
    }
    
    return $protocol . $_SERVER['HTTP_HOST'] . $request_uri;
}

function auth($name)
{
    return hash_hmac('sha1', $name, AUTH_SECRET);
}

function return_json($data)
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function return_text($text)
{
    header('Content-Type: text/plain; charset=utf-8');
    echo $text;
    exit;
}

function return_redirect($url)
{
    header('Location: ' . $url);
    exit;
}

function return_error($message)
{
    http_response_code(404);
    return_json(['error' => $message]);
}