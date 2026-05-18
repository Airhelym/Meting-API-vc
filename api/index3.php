<?php
// 生产环境：关闭错误显示，防止路径泄露
ini_set('display_errors', 'Off');
error_reporting(0);

// 允许跨站
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

// 设置中文歌词
define('TLYRIC', true);
// 设置歌单文件缓存及时间
define('CACHE', false);
define('CACHE_TIME', 86400);
// 设置短期缓存 - 需要安装 apcu
define('APCU_CACHE', false);
// 设置 AUTH 密钥
define('AUTH', false);
define('AUTH_SECRET', 'meting-secret');

// 检查参数是否缺失
if (!isset($_GET['type']) || !isset($_GET['id'])) {
    include __DIR__ . '/public/index.php';
    exit;
}

$server = isset($_GET['server']) ? $_GET['server'] : 'netease';
$type = $_GET['type'];
$id = $_GET['id'];

// 验证 AUTH（如果开启）
if (AUTH) {
    $auth = isset($_GET['auth']) ? $_GET['auth'] : '';
    if (in_array($type, ['url', 'pic', 'lrc', 'lyric'])) {
        if ($auth == '' || $auth != auth($server . $type . $id)) {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden']);
            exit;
        }
    }
}

// 设置 API 路径（使用协议自适应）
define('API_URI', api_uri());

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
        $api->header['Cookie'] = 'uin=1234567890; ' . $currentCookie;
    }
}

// 处理不同的 type
switch ($type) {
    case 'playlist':
        handle_playlist($api, $server, $id);
        break;

    case 'search':
        handle_search($api, $server, $id);
        break;

    case 'album':
        handle_album($api, $server, $id);
        break;

    case 'artist':
        handle_artist($api, $server, $id);
        break;

    case 'song':
        handle_song($api, $server, $id);
        break;

    case 'name':
        handle_name($api, $id);
        break;

    case 'url':
        handle_url($api, $server, $id);
        break;

    case 'pic':
        handle_pic($api, $server, $id);
        break;

    case 'lrc':
    case 'lyric':
        handle_lyric($api, $server, $id);
        break;

    default:
        return_error('Unknown type: ' . $type);
        break;
}

// ============================================================================
// 协议自适应函数（独立封装，提高可复用性）
// ============================================================================

/**
 * 检测当前请求是否使用 HTTPS
 * 支持反向代理场景（X-Forwarded-Proto 头）
 * @return bool
 */
function is_https_request()
{
    return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ||
           (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
}

/**
 * 获取当前请求的协议前缀
 * @return string 'https://' 或 'http://'
 */
function get_current_scheme()
{
    return is_https_request() ? 'https://' : 'http://';
}

/**
 * 将 URL 转换为与当前请求相同的协议
 * 如果请求是 HTTPS，则强制转换为 HTTPS；否则保持原样
 * @param string $url 原始 URL
 * @param bool $force_https 是否强制转换为 HTTPS（可选）
 * @return string 协议自适应后的 URL
 */
function adapt_url_protocol($url, $force_https = false)
{
    if (empty($url)) {
        return $url;
    }
    
    // 如果请求是 HTTPS 或强制要求 HTTPS，则转换
    if (is_https_request() || $force_https) {
        return str_replace('http://', 'https://', $url);
    }
    
    return $url;
}

/**
 * 构建 API 端点 URL（协议自适应）
 * @param string $server 音乐源
 * @param string $type 类型
 * @param string $id ID
 * @param bool $include_auth 是否包含 auth 参数
 * @return string 完整的 API URL
 */
function build_api_url($server, $type, $id, $include_auth = false)
{
    $url = API_URI . '?server=' . $server . '&type=' . $type . '&id=' . $id;
    if ($include_auth && AUTH) {
        $url .= '&auth=' . auth($server . $type . $id);
    }
    return $url;
}

// ============================================================================
// 业务处理函数（每个 type 独立函数，提高可维护性）
// ============================================================================

function handle_playlist($api, $server, $id)
{
    // 缓存处理
    if (CACHE) {
        $file_path = __DIR__ . '/cache/playlist/' . $server . '_' . $id . '.json';
        if (file_exists($file_path) && (time() - filemtime($file_path) < CACHE_TIME)) {
            header('Content-Type: application/json; charset=utf-8');
            echo file_get_contents($file_path);
            exit;
        }
    }
    
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
            'url'    => build_api_url($song->source, 'url', $song->url_id, true),
            'pic'    => build_api_url($song->source, 'pic', $song->pic_id, true),
            'lrc'    => build_api_url($song->source, 'lrc', $song->lyric_id, true)
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
}

function handle_search($api, $server, $id)
{
    $keyword = $id;
    $option = [];
    if (isset($_GET['page'])) $option['page'] = (int)$_GET['page'];
    if (isset($_GET['limit'])) $option['limit'] = (int)$_GET['limit'];
    
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
            'url'    => build_api_url($song->source, 'url', $song->url_id, true),
            'pic'    => build_api_url($song->source, 'pic', $song->pic_id, true),
            'lrc'    => build_api_url($song->source, 'lrc', $song->lyric_id, true)
        ];
    }
    
    return_json($results);
}

function handle_album($api, $server, $id)
{
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
            'url'    => build_api_url($song->source, 'url', $song->url_id, true),
            'pic'    => build_api_url($song->source, 'pic', $song->pic_id, true),
            'lrc'    => build_api_url($song->source, 'lrc', $song->lyric_id, true)
        ];
    }
    
    return_json($album_songs);
}

function handle_artist($api, $server, $id)
{
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
            'url'    => build_api_url($song->source, 'url', $song->url_id, true),
            'pic'    => build_api_url($song->source, 'pic', $song->pic_id, true),
            'lrc'    => build_api_url($song->source, 'lrc', $song->lyric_id, true)
        ];
    }
    
    return_json($artist_songs);
}

function handle_song($api, $server, $id)
{
    $data = $api->song($id);
    if ($data == '[]' || $data == 'null') {
        return_error('Unknown song ID');
    }
    
    $song = json_decode($data)[0];
    $response = [
        'name'   => $song->name,
        'artist' => implode('/', $song->artist),
        'url'    => build_api_url($server, 'url', $song->url_id, true),
        'pic'    => build_api_url($server, 'pic', $song->pic_id, true),
        'lrc'    => build_api_url($server, 'lrc', $song->lyric_id, true)
    ];
    
    return_json([$response]);
}

function handle_name($api, $id)
{
    $data = $api->song($id);
    if ($data == '[]' || $data == 'null') {
        return_error('Unknown song ID');
    }
    
    $song = json_decode($data)[0];
    return_text($song->name);
}

function handle_url($api, $server, $id)
{
    // 短期缓存处理
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
    
    // 协议自适应转换
    $m_url = adapt_url_protocol($m_url);
    
    // 缓存 URL
    if (APCU_CACHE) {
        apcu_store($apcu_key, ['url' => $m_url], 600);
    }
    
    return_redirect($m_url);
}

function handle_pic($api, $server, $id)
{
    if (APCU_CACHE) {
        $apcu_key = $server . '_pic_' . $id;
        if (apcu_exists($apcu_key)) {
            $pic_data = apcu_fetch($apcu_key);
            return_redirect($pic_data->url);
        }
    }
    
    $size = isset($_GET['size']) ? (int)$_GET['size'] : 90;
    $pic_url = json_decode($api->pic($id, $size))->url;
    
    // 协议自适应转换
    $pic_url = adapt_url_protocol($pic_url);
    
    if (APCU_CACHE) {
        apcu_store($apcu_key, ['url' => $pic_url], 36000);
    }
    
    return_redirect($pic_url);
}

function handle_lyric($api, $server, $id)
{
    if (APCU_CACHE) {
        $apcu_key = $server . '_lrc_' . $id;
        if (apcu_exists($apcu_key)) {
            $lrc_text = apcu_fetch($apcu_key);
            return_text($lrc_text);
        }
    }
    
    $lrc_data = json_decode($api->lyric($id));
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
}

// ============================================================================
// 辅助函数
// ============================================================================

function api_uri()
{
    return get_current_scheme() . $_SERVER['HTTP_HOST'] . strtok($_SERVER['REQUEST_URI'], '?');
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