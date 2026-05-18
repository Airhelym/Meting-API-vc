<?php
// 生产环境：关闭错误显示，防止路径泄露
ini_set('display_errors', 'Off');
error_reporting(0);

// 允许跨站
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

// 检查参数是否缺失
if (!isset($_GET['type']) || !isset($_GET['id'])) {
    include __DIR__ . '/public/index.php';
    exit;
}

// 设置 API 路径（使用协议自适应函数）
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

// 包含 Meting 核心库
include __DIR__ . '/src/Meting.php';
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
    case 'playlist':
        // 缓存处理
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
        // 获取搜索关键词
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

    case 'song':
        // 获取单曲信息
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
        // 获取歌曲名称
        $data = $api->song($id);
        if ($data == '[]' || $data == 'null') {
            return_error('Unknown song ID');
        }
        $song = json_decode($data)[0];
        return_text($song->name);
        break;

    case 'url':
        // 获取音频直链
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
        // 使用协议自适应函数处理 URL
        $m_url = adapt_url_protocol($m_url, $server);
        if (APCU_CACHE) {
            apcu_store($apcu_key, ['url' => $m_url], 600);
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
        // 使用协议自适应函数处理 URL
        $pic_url = adapt_url_protocol($pic_url, $server);
        if (APCU_CACHE) {
            apcu_store($apcu_key, ['url' => $pic_url], 36000);
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

// ============================================================================
// 协议自适应函数（独立封装，提高可复用性和可维护性）
// ============================================================================

/**
 * 检测当前请求是否为 HTTPS
 * 支持反向代理场景（如 Nginx、Cloudflare 等）
 * @return bool
 */
function is_https_request()
{
    // 检查直接 HTTPS 连接
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        return true;
    }
    // 检查反向代理的 HTTPS 标识
    if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
        return true;
    }
    // 检查其他常见的代理头
    if (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') {
        return true;
    }
    // 检查端口
    if (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) {
        return true;
    }
    return false;
}

/**
 * 获取当前请求的协议方案
 * @return string 'https://' 或 'http://'
 */
function get_current_scheme()
{
    return is_https_request() ? 'https://' : 'http://';
}

/**
 * 自适应 URL 协议
 * 根据当前请求协议调整目标 URL 的协议
 * 如果请求是 HTTPS，则强制目标 URL 为 HTTPS
 * 如果请求是 HTTP，则保持原样或回退到 HTTP
 * @param string $url 原始 URL
 * @param string $server 音乐源服务器（netease, tencent 等）
 * @return string 调整后的 URL
 */
function adapt_url_protocol($url, $server = '')
{
    // 如果 URL 为空，直接返回
    if (empty($url)) {
        return $url;
    }
    
    // 仅当当前请求为 HTTPS 时，才强制转换媒体链接为 HTTPS
    // 这样可以避免 HTTP 请求下强制 HTTPS 导致的混合内容问题或加载失败
    if (is_https_request()) {
        // 网易云和腾讯音乐支持 HTTPS
        if ($server == 'netease' || $server == 'tencent' || $server == '') {
            $url = str_replace('http://', 'https://', $url);
        }
    }
    // 如果当前请求是 HTTP，则不强制转换，保持原链接或回退
    // 这样可以在低速环境下使用 HTTP 提高加载速度
    
    return $url;
}

// ============================================================================
// 辅助函数
// ============================================================================

function api_uri()
{
    // 使用协议自适应函数生成 API URI
    $scheme = get_current_scheme();
    return $scheme . $_SERVER['HTTP_HOST'] . strtok($_SERVER['REQUEST_URI'], '?');
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