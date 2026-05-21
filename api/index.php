<?php
// vercel.json 放在根目录，其他文件保持相对关系放在 api 目录，本文件重命名为 index.php
// 生产环境：关闭错误显示，防止路径泄露
ini_set('display_errors', 'Off');
error_reporting(0);
// 允许跨域
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// 检查参数是否缺失
if (!isset($_GET['type']) || !isset($_GET['id'])) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Missing required parameters: type and id']);
    exit;
}

// ================= 协议与 API 路径适配 =================
function is_https_request() {
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') return true;
    if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') return true;
    if (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') return true;
    if (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) return true;
    return false;
}

function api_uri() {
    $scheme = is_https_request() ? 'https://' : 'http://';
    // Vercel 路由重写后，REQUEST_URI 仍保留原始路径，直接截取即可
    $uri = strtok($_SERVER['REQUEST_URI'], '?');
    return $scheme . $_SERVER['HTTP_HOST'] . $uri;
}
define('API_URI', api_uri());

// ================= 核心配置 =================
define('TLYRIC', true);
define('CACHE', false);          // Vercel 无持久磁盘，文件缓存意义不大，建议 false
define('CACHE_TIME', 86400);
define('APCU_CACHE', false);     // Vercel 不支持 APCu 扩展
define('AUTH', false);
define('AUTH_SECRET', 'meting-secret');

$server = isset($_GET['server']) ? $_GET['server'] : 'netease';
$type   = $_GET['type'];
$id     = $_GET['id'];

// ================= 自定义 Server 处理逻辑 =================
$CUSTOM_SERVERS = [
    'lolic' => [
        'api'           => 'https://apis0-meting.lolic.dpdns.org/player-assets/api.php',
        'support_types' => ['song', 'playlist'],
        'direct_return' => true,
        'timeout'       => 8, // Vercel 免费层默认超时 10s，留 2s 余量
    ]
];

if (isset($CUSTOM_SERVERS[$server])) {
    $config = $CUSTOM_SERVERS[$server];
    if (!empty($config['support_types']) && !in_array($type, $config['support_types'])) {
        return_error('Type "'.$type.'" not supported for server "'.$server.'"');
    }

    $remote_url = $config['api'] . '?' . http_build_query($_GET);
    $ch = curl_init($remote_url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $config['timeout'] ?? 8,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_SSL_VERIFYPEER => true, // 生产环境建议开启证书验证
        CURLOPT_HTTPHEADER     => [
            'User-Agent: ' . ($_SERVER['HTTP_USER_AGENT'] ?? 'Meting-API/1.0'),
            'Accept: application/json, text/plain, */*',
        ],
    ]);
    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error     = curl_error($ch);
    curl_close($ch);

    if ($http_code === 200 && $response !== false) {
        if (in_array($type, ['url', 'pic'])) {
            $json = json_decode($response, true);
            if (json_last_error() === JSON_ERROR_NONE && isset($json['url'])) {
                return_redirect($json['url']);
            }
            if (filter_var($response, FILTER_VALIDATE_URL)) {
                return_redirect($response);
            }
        }
        if ($type === 'lrc' || $type === 'lyric') {
            $json = json_decode($response, true);
            if (json_last_error() === JSON_ERROR_NONE && isset($json['lyric'])) {
                return_text($json['lyric']);
            }
            return_text($response);
        }
        if ($config['direct_return'] ?? true) {
            $json = json_decode($response, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                if ($type === 'song' && !isset($json[0])) {
                    return_json([$json]);
                }
                return_json($json);
            }
        }
    }
    http_response_code(502);
    return_json(['error' => 'Custom server unavailable', 'server' => $server, 'details' => $error ?: "HTTP $http_code"]);
    exit;
}

// ================= 原有 Meting 逻辑 =================
// 验证 AUTH
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

// 注意 Linux 大小写敏感
include __DIR__ . '/src/Meting.php';
use Metowolf\Meting;

$api = new Meting($server);
$api->format(true);

if ($server === 'tencent') {
    $currentCookie = $api->header['Cookie'] ?? '';
    if (!preg_match('/uin=\d+/', $currentCookie)) {
        $api->header['Cookie'] = 'uin=1234567890; ' . $currentCookie;
    }
}

switch ($type) {
    case 'playlist':
        if (CACHE) {
            $file_path = __DIR__ . '/cache/playlist/' . $server . '_' . $id . '.json';
            if (file_exists($file_path) && (time() - filemtime($file_path) < CACHE_TIME)) {
                return_json(json_decode(file_get_contents($file_path)));
            }
        }
        $data = $api->playlist($id);
        if ($data == '[]' || $data == 'null') return_error('Unknown playlist ID');
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
        if (CACHE) {
            $cache_dir = dirname($file_path);
            if (!is_dir($cache_dir)) mkdir($cache_dir, 0755, true);
            $playlist_json = json_encode($playlist, JSON_UNESCAPED_UNICODE); // 修复未定义变量
            file_put_contents($file_path, $playlist_json);
        }
        return_json($playlist);
        break;
    case 'search':
        $keyword = $id;
        $option = [];
        if (isset($_GET['page']))  $option['page']  = (int)$_GET['page'];
        if (isset($_GET['limit'])) $option['limit'] = (int)$_GET['limit'];
        $data = $api->search($keyword, $option);
        if ($data == '[]' || $data == 'null') return_error('No search results');
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
    case 'artist':
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : ($type === 'artist' ? 50 : 100);
        $data = $api->{$type}($id, $limit);
        if ($data == '[]' || $data == 'null') return_error('Unknown ' . $type . ' ID or no songs');
        $songs = json_decode($data);
        $list = [];
        foreach ($songs as $song) {
            $list[] = [
                'name'   => $song->name,
                'artist' => implode('/', $song->artist),
                'url'    => API_URI . '?server=' . $song->source . '&type=url&id=' . $song->url_id . (AUTH ? '&auth=' . auth($song->source . 'url' . $song->url_id) : ''),
                'pic'    => API_URI . '?server=' . $song->source . '&type=pic&id=' . $song->pic_id . (AUTH ? '&auth=' . auth($song->source . 'pic' . $song->pic_id) : ''),
                'lrc'    => API_URI . '?server=' . $song->source . '&type=lrc&id=' . $song->lyric_id . (AUTH ? '&auth=' . auth($song->source . 'lrc' . $song->lyric_id) : '')
            ];
        }
        return_json($list);
        break;
    case 'song':
    case 'name':
        $data = $api->song($id);
        if ($data == '[]' || $data == 'null') return_error('Unknown song ID');
        $song = json_decode($data)[0];
        $res = [
            'name'   => $song->name,
            'artist' => implode('/', $song->artist),
            'url'    => API_URI . '?server=' . $server . '&type=url&id=' . $song->url_id . (AUTH ? '&auth=' . auth($server . 'url' . $song->url_id) : ''),
            'pic'    => API_URI . '?server=' . $server . '&type=pic&id=' . $song->pic_id . (AUTH ? '&auth=' . auth($server . 'pic' . $song->pic_id) : ''),
            'lrc'    => API_URI . '?server=' . $server . '&type=lrc&id=' . $song->lyric_id . (AUTH ? '&auth=' . auth($server . 'lrc' . $song->lyric_id) : '')
        ];
        if ($type === 'name') return_text($song->name);
        return_json([$res]);
        break;
    case 'url':
        if (APCU_CACHE) {
            $apcu_key = $server . '_url_' . $id;
            if (apcu_exists($apcu_key)) return_redirect(apcu_fetch($apcu_key)->url);
        }
        $br = isset($_GET['br']) ? (int)$_GET['br'] : 320;
        $m_url = json_decode($api->url($id, $br))->url;
        if ($m_url == '') return_error('Failed to get audio URL');
        $m_url = str_replace('http://', 'https://', $m_url); // 强制 HTTPS 适配现代播放器
        if (APCU_CACHE) apcu_store($apcu_key, ['url' => $m_url], 600);
        return_redirect($m_url);
        break;
    case 'pic':
        if (APCU_CACHE) {
            $apcu_key = $server . '_pic_' . $id;
            if (apcu_exists($apcu_key)) return_redirect(apcu_fetch($apcu_key)->url);
        }
        $size = isset($_GET['size']) ? (int)$_GET['size'] : 90;
        $pic_url = json_decode($api->pic($id, $size))->url;
        $pic_url = str_replace('http://', 'https://', $pic_url);
        if (APCU_CACHE) apcu_store($apcu_key, ['url' => $pic_url], 36000);
        return_redirect($pic_url);
        break;
    case 'lrc':
    case 'lyric':
        if (APCU_CACHE) {
            $apcu_key = $server . '_lrc_' . $id;
            if (apcu_exists($apcu_key)) return_text(apcu_fetch($apcu_key));
        }
        $lrc_data = json_decode($api->lyric($id));
        $lyric_content = $lrc_data->lyric ?? '';
        $tlyric_content = $lrc_data->tlyric ?? '';
        if ($lyric_content == '') {
            $lrc = '[00:00.00] 这似乎是一首纯音乐呢，请尽情欣赏它吧！';
        } else if ($tlyric_content == '' || !TLYRIC) {
            $lrc = $lyric_content;
        } else {
            $lrc_arr = explode("\n", $lyric_content);
            $lrc_cn_arr = explode("\n", $tlyric_content);
            $lrc_cn_map = [];
            foreach ($lrc_cn_arr as $line) {
                if (trim($line) == '') continue;
                $parts = explode(']', $line, 2);
                if (count($parts) == 2) $lrc_cn_map[$parts[0] . ']'] = trim($parts[1]);
            }
            foreach ($lrc_arr as $i => $line) {
                if (trim($line) == '') continue;
                $parts = explode(']', $line, 2);
                if (count($parts) == 2 && isset($lrc_cn_map[$parts[0] . ']']) && $lrc_cn_map[$parts[0] . ']'] != '//') {
                    $lrc_arr[$i] .= ' (' . $lrc_cn_map[$parts[0] . ']'] . ')';
                }
            }
            $lrc = implode("\n", $lrc_arr);
        }
        if (APCU_CACHE) apcu_store($apcu_key, $lrc, 36000);
        return_text($lrc);
        break;
    default:
        return_error('Unknown type: ' . $type);
}

// ================= 辅助函数 =================
function auth($name) { return hash_hmac('sha1', $name, AUTH_SECRET); }
function return_json($data) { header('Content-Type: application/json; charset=utf-8'); echo json_encode($data, JSON_UNESCAPED_UNICODE); exit; }
function return_text($text) { header('Content-Type: text/plain; charset=utf-8'); echo $text; exit; }
function return_redirect($url) { header('Location: ' . $url); exit; }
function return_error($message) { http_response_code(404); return_json(['error' => $message]); }