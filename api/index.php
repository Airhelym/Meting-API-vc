<?php
// 生产环境：关闭错误显示，防止路径泄露 (来自 index-1)
ini_set('display_errors', 'Off');
error_reporting(0);

// 设置 API 路径
define('API_URI', api_uri());
// 设置中文歌词
define('TLYRIC', true);
// 设置歌单文件缓存及时间
define('CACHE', false);
define('CACHE_TIME', 86400);
// 设置短期缓存 - 需要安装 apcu
define('APCU_CACHE', false);
// 设置 AUTH 密钥 - 更改'meting-secret'
define('AUTH', false);
define('AUTH_SECRET', 'meting-secret');

// 检查参数是否缺失 (来自 index-2，保留公共页面 fallback)
if (!isset($_GET['type']) || !isset($_GET['id'])) {
    include __DIR__ . '/public/index.php';
    exit;
}

$server = isset($_GET['server']) ? $_GET['server'] : 'netease'; // 默认网易云
$type = $_GET['type'];
$id = $_GET['id'];

// 验证 AUTH（如果开启）
if (AUTH) {
    $auth = isset($_GET['auth']) ? $_GET['auth'] : '';
    // 对于需要鉴权的资源类型（url, pic, lrc），检查 auth 参数
    if (in_array($type, ['url', 'pic', 'lrc', 'lyric'])) {
        if ($auth == '' || $auth != auth($server . $type . $id)) {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden']);
            exit;
        }
    }
}

// 允许跨站 (来自 index-2)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

// 包含 Meting 核心库 (保留 index-2 的路径大小写)
include __DIR__ . '/src/Meting.php';
use Metowolf\Meting;

// 创建 Meting 实例
$api = new Meting($server);
$api->format(true);

// 修复腾讯 Cookie 缺少 uin 的问题 (来自 index-1)
if ($server === 'tencent') {
    $currentCookie = $api->header['Cookie'] ?? '';
    if (!preg_match('/uin=\d+/', $currentCookie)) {
        // 注入一个 guest uin
        $api->header['Cookie'] = 'uin=1234567890; ' . $currentCookie;
    }
}

// 处理不同的 type (融合 index-1 的 switch 结构与 index-2 的逻辑)
switch ($type) {
    case 'playlist':
        // 缓存处理 (来自 index-2)
        if (CACHE) {
            $file_path = __DIR__ . '/cache/playlist/' . $server . '_' . $id . '.json';
            if (file_exists($file_path) && (time() - filemtime($file_path) < CACHE_TIME)) {
                header('Content-Type: application/json; charset=utf-8');
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
            $playlist_json = json_encode($playlist, JSON_UNESCAPED_UNICODE);
            file_put_contents($file_path, $playlist_json);
        }
        return_json($playlist);
        break;

    case 'search':
        // 获取搜索关键词 (来自 index-1)
        $keyword = $id;
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
        // 获取专辑 (来自 index-1)
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
        // 获取艺术家热门歌曲 (来自 index-1，功能增强)
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

    case 'song':
        // 获取单曲信息 (来自 index-1，结构更完整)
        $data = $api->song($id);
        if ($data == '[]' || $data == 'null') {
            return_error('Unknown song ID');
        }
        $song = json_decode($data)[0];
        $response = [
            'name'   => $song->name,
            'artist' => implode('/', $song->artist),
            'url'    => API_URI . '?server=' . $server . '&type=url&id=' . $song->url_id . (AUTH ? '&auth=' . auth($server . 'url' . $song->url_id) : ''),
            'pic'    => API_URI . '?server=' . $server . '&type=pic&id=' . $song->pic_id . (AUTH ? '&auth=' . auth($server . 'pic' . $song->pic_id) : ''),
            'lrc'    => API_URI . '?server=' . $server . '&type=lrc&id=' . $song->lyric_id . (AUTH ? '&auth=' . auth($server . 'lrc' . $song->lyric_id) : '')
        ];
        return_json([$response]);
        break;
    
    case 'name':
        // 获取歌曲名称 (来自 index-2 特有功能)
        $data = $api->song($id);
        if ($data == '[]' || $data == 'null') {
            return_error('Unknown song ID');
        }
        $song = json_decode($data)[0];
        return_text($song->name);
        break;

    case 'url':
        // 获取音频直链 (来自 index-1，带 APCU 缓存)
        if (APCU_CACHE) {
            $apcu_key = $server . '_url_' . $id;
            if (apcu_exists($apcu_key)) {
                $url_data = apcu_fetch($apcu_key);
                return_redirect($url_data->url);
            }
        }
        $br = isset($_GET['br']) ? (int)$_GET['br'] : 320;
        $m_url = json_decode($api->url($id, $br))->url;
        if ($m_url == '') {
            return_error('Failed to get audio URL');
        }
        // 特殊处理：强制 HTTPS
        if ($server == 'netease' || $server == 'tencent') {
            $m_url = str_replace('http://', 'https://', $m_url);
        }
        if (APCU_CACHE) {
            apcu_store($apcu_key, ['url' => $m_url], 600);
        }
        return_redirect($m_url);
        break;

    case 'pic':
        // 获取图片直链 (来自 index-1，带 APCU 缓存)
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
            apcu_store($apcu_key, ['url' => $pic_url], 36000);
        }
        return_redirect($pic_url);
        break;

    case 'lrc':
    case 'lyric':
        // 获取歌词 (来自 index-1，修复解析逻辑)
        if (APCU_CACHE) {
            $apcu_key = $server . '_lrc_' . $id;
            if (apcu_exists($apcu_key)) {
                $lrc_text = apcu_fetch($apcu_key);
                return_text($lrc_text);
            }
        }
        $lrc_data = json_decode($api->lyric($id));
        // 兼容不同版本的返回结构
        $lyric_content = $lrc_data->lyric ?? '';
        $tlyric_content = $lrc_data->tlyric ?? '';

        if ($lyric_content == '') {
            $lrc = '[00:00.00] 这似乎是一首纯音乐呢，请尽情欣赏它吧！';
        } else if ($tlyric_content == '' || !TLYRIC) {
            $lrc = $lyric_content;
        } else {
            // 合并中译歌词
            $lrc_arr = explode("\n", $lyric_content);
            $lrc_cn_arr = explode("\n", $tlyric_content);
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

// --- 辅助函数 (来自 index-1，优化结构) ---
function api_uri()
{
    // 使用 index-2 的逻辑以适应当前入口文件结构
    return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') 
           . $_SERVER['HTTP_HOST'] 
           . strtok($_SERVER['REQUEST_URI'], '?');
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