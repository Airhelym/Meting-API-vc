<?php
// 生产环境：关闭错误显示，防止路径泄露

ini_set('display_errors', 'Off');
error_reporting(0);

// 可选：将错误记录到日志
// ini_set('log_errors', 'On');
// ini_set('error_log', '/path/to/your/error.log');

// 允许跨域。若为 Vercel 环境或未配置 .htaccess 重写则取消注释
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if (!isset($_GET['type']) || !isset($_GET['id'])) {
    header('Content-Type: text/html; charset=utf-8');
    echo <<<'HTML'
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Meting API</title>
  <style>
    :root { --bg: #f8fafc; --card: #ffffff; --text: #0f172a; --text-light: #64748b; --primary: #2563eb; --primary-hover: #1d4ed8; --code-bg: #DCDCDC; --code-text: #e2e8f0; --border: #e2e8f0; --success: #10b981; }
    * { box-sizing: border-box; }
    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; background: var(--bg); color: var(--text); margin: 0; padding: 2rem 1rem; line-height: 1.6; }
    .container { max-width: 960px; margin: 0 auto; }
    header { text-align: center; margin-bottom: 2.5rem; }
    h1 { margin: 0; font-size: 2rem; color: var(--primary); letter-spacing: -0.5px; }
    .subtitle { color: var(--text-light); margin-top: 0.5rem; font-size: 0.95rem; }
    .card { background: var(--card); border-radius: 12px; padding: 1.5rem; margin-bottom: 1.5rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); border: 1px solid var(--border); }
    .card h2 { margin-top: 0; font-size: 1.25rem; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem; margin-bottom: 1rem; color: var(--text); }
    table { width: 100%; border-collapse: collapse; font-size: 0.95rem; }
    th, td { padding: 0.75rem 1rem; text-align: left; border-bottom: 1px solid var(--border); }
    th { background: #f1f5f9; font-weight: 600; color: var(--text-light); }
    code { background: #f1f5f9; padding: 0.2em 0.4em; border-radius: 4px; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 0.9em; color: #d946ef; }
    .code-block { background: var(--code-bg); color: var(--code-text); padding: 1rem; border-radius: 8px; overflow-x: auto; position: relative; font-family: ui-monospace, monospace; font-size: 0.9rem; margin: 0.5rem 0; }
    .badge { display: inline-block; padding: 0.15rem 0.5rem; border-radius: 99px; font-size: 0.75rem; font-weight: 600; }
    .badge-req { background: #fee2e2; color: #dc2626; }
    .badge-opt { background: #dbeafe; color: #2563eb; }
    .example-item { background: #f8fafc; padding: 1rem; margin: 1rem 0; border-radius: 8px; border: 1px solid var(--border); transition: transform 0.2s; }
    .example-item:hover { transform: translateY(-2px); }
    .example-item h3 { margin: 0 0 0.5rem; font-size: 1rem; color: var(--text); }
    .base-url { background: #f1f5f9; padding: 0.5rem 1rem; border-radius: 6px; font-family: monospace; color: var(--primary); word-break: break-all; display: block; text-align: center; }
    footer { text-align: center; margin-top: 2rem; color: var(--text-light); font-size: 0.85rem; }
    @media (max-width: 640px) {
      table { font-size: 0.85rem; display: block; overflow-x: auto; }
      th, td { padding: 0.5rem; white-space: nowrap; }
    }
  </style>
</head>
<body>
  <div class="container">
    <header>
      <h1>Meting API</h1>
      <p class="subtitle">接口参数说明</p>
    </header>

    <div class="card">
      <h2>当前地址</h2>
      <span class="base-url" id="baseUrl"></span>
    </div>

    <div class="card">
      <h2>请求参数</h2>
      <table>
        <thead><tr><th>参数名</th><th>类型</th><th>必填</th><th>说明</th></tr></thead>
        <tbody>
          <tr><td><code>server</code></td><td>String</td><td><span class="badge badge-opt">可选</span></td><td>音乐平台：netease(默认) / tencent / kugou / baidu</td></tr>
          <tr><td><code>type</code></td><td>String</td><td><span class="badge badge-req">必填</span></td><td>请求类型：song / playlist / search（部分） / album / artist / url / pic / lrc</td></tr>
          <tr><td><code>id</code></td><td>String/Int</td><td><span class="badge badge-req">必填</span></td><td>歌曲 ID、歌单 ID、专辑 ID、搜索关键词或艺术家 ID</td></tr>
          <tr><td><code>auth</code></td><td>String</td><td><span class="badge badge-opt">可选</span></td><td>接口鉴权签名（若已开启 AUTH 功能）</td></tr>
          <tr><td><code>br</code></td><td>Int</td><td><span class="badge badge-opt">可选</span></td><td>音频码率，默认 320（仅 type=url 有效）</td></tr>
          <tr><td><code>size</code></td><td>Int</td><td><span class="badge badge-opt">可选</span></td><td>封面尺寸，默认 90（仅 type=pic 有效）</td></tr>
          <tr><td><code>page</code></td><td>Int</td><td><span class="badge badge-opt">可选</span></td><td>页码，默认 1（仅 type=search/artist 有效）</td></tr>
          <tr><td><code>limit</code></td><td>Int</td><td><span class="badge badge-opt">可选</span></td><td>每页数量，默认 50（仅 type=search/artist 有效）</td></tr>
        </tbody>
      </table>
    </div>

    <div class="card">
      <h2>调用示例</h2>
      <div class="example-grid">
        <div class="example-item">
          <h3>获取单曲信息</h3>
          <div class="code-block"><code>GET https://your-domain/api?server=netease&type=song&id=123456</code></div>
        </div>
        <div class="example-item">
          <h3>获取歌单歌曲</h3>
          <div class="code-block"><code>GET https://your-domain/api?server=netease&type=playlist&id=3778678</code></div>
        </div>
        <div class="example-item">
          <h3>搜索歌曲（部分支持）</h3>
          <div class="code-block"><code>GET https://your-domain/api?server=netease&type=search&id=周杰伦&page=1&limit=10</code></div>
        </div>
        <div class="example-item">
          <h3>获取直链</h3>
          <div class="code-block"><code>GET https://your-domain/api?server=netease&type=url&id=123456&br=320</code></div>
        </div>
      </div>
    </div>

    <div class="card">
      <h2>返回示例</h2>
      <div class="code-block">
        <code>
            [
              {
                "name": "晴天",
                "artist": "周杰伦",
                "url": "https://your-domain/api?server=netease&type=url&id=...",
                "pic": "https://your-domain/api?server=netease&type=pic&id=...",
                "lrc": "https://your-domain/api?server=netease&type=lrc&id=..."
              }
            ]
        </code>
      </div>
    </div>

    <footer>
      <p>基于 <a href="https://github.com/metowolf/Meting" target="_blank" style="color:var(--primary);text-decoration:none;">Meting</a> 和 <a href="https://github.com/injahow/meting-api" target="_blank" style="color:var(--primary);text-decoration:none;">injahow/meting-api</a> 二次开发构建</p>
    </footer>
  </div>

  <script>
    // 动态渲染接口地址
    document.getElementById('baseUrl').textContent = window.location.origin + window.location.pathname.replace('api.php', 'api');
    
    // 替换示例中的占位域名
    document.querySelectorAll('.code-block code').forEach(el => {
      if(el.textContent.includes('https://your-domain')) {
        el.textContent = el.textContent.replace('https://your-domain/api', window.location.href.split('?')[0]);
      }
    });
  </script>
</body>
</html>
HTML;
    exit;
}

// 防止 Vercel 环境出现意外情况
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

// 设置API路径
define('API_URI', api_uri());
// 设置中文歌词
define('TLYRIC', true);
// 设置歌单文件缓存及时间
define('CACHE', false); // 若为 Vercel 环境，由于无持久磁盘，文件缓存意义不大，建议 false
define('CACHE_TIME', 86400);
// 设置短期缓存-需要安装apcu
define('APCU_CACHE', false); // Vercel 不支持 APCu 扩展，若为 Vercel 环境或没装 apcu 则设为 false
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

// 自定义 Server 配置，仅支持直接返回 Meting 格式的 API
$CUSTOM_SERVERS = [
//    'lolic' => [
//        'api' => 'https://example.com/api.php',  // 自定义 API 地址
//        'support_types' => ['song', 'playlist'], // 支持的 type 类型
//        'direct_return' => true,                  // 是否直接返回响应（不二次格式化）
//        'timeout' => 20,                          // 请求超时时间（秒）。Vercel 免费计划默认超时 10s，建议设为 8 秒
//    ],
    'lolic' => [
        'api' => 'https://apis0-meting.lolic.dpdns.org/player-assets/api.php',
        'support_types' => ['song', 'playlist'],
        'direct_return' => true,
        'timeout' => 20,
    ]
    // 可扩展其他自定义 server...
];

// ===== 自定义 Server 拦截 =====
$server = isset($_GET['server']) ? $_GET['server'] : 'netease';
$type = $_GET['type'];
$id = $_GET['id'];

// 拦截自定义 server 请求
if (isset($CUSTOM_SERVERS[$server])) {
    $config = $CUSTOM_SERVERS[$server];
    
    // 检查 type 是否支持
    if (!empty($config['support_types']) && !in_array($type, $config['support_types'])) {
        return_error('Type "'.$type.'" not supported for server "'.$server.'"');
    }
    
    // 构造转发请求
    $remote_url = $config['api'] . '?' . http_build_query($_GET);
    
    // 执行转发
    $ch = curl_init($remote_url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $config['timeout'] ?? 20,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_SSL_VERIFYPEER => false, // 生产环境建议验证证书
        CURLOPT_HTTPHEADER => [
            'User-Agent: ' . ($_SERVER['HTTP_USER_AGENT'] ?? 'Meting-API/1.0'),
            'Accept: application/json, text/plain, */*',
        ],
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $error = curl_error($ch);
    curl_close($ch);
    
    // 处理响应
    if ($http_code === 200 && $response !== false) {
        if (in_array($type, ['url', 'pic'])) {
            // 如果自定义 API 返回的是重定向链接
            $json = json_decode($response, true);
            if (json_last_error() === JSON_ERROR_NONE && isset($json['url'])) {
                return_redirect($json['url']);
            }
            // 否则尝试直接重定向
            if (filter_var($response, FILTER_VALIDATE_URL)) {
                return_redirect($response);
            }
        }
        
        if ($type === 'lrc' || $type === 'lyric') {
            // 歌词可能是纯文本或 JSON
            $json = json_decode($response, true);
            if (json_last_error() === JSON_ERROR_NONE && isset($json['lyric'])) {
                return_text($json['lyric']);
            }
            return_text($response);
        }
        
        if ($config['direct_return'] ?? true) {
            $json = json_decode($response, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                // 确保返回格式统一
                if ($type === 'song' && !isset($json[0])) {
                    return_json([$json]);
                }
                return_json($json);
            }
        }
        // 如需格式转换，可在此添加自己的映射逻辑
    }
    
    http_response_code(502);
    return_json([
        'error' => 'Custom server unavailable',
        'server' => $server,
        'details' => $error ?: "HTTP $http_code"
    ]);
    exit;
}
// ===== 自定义 Server 拦截 结束 =====

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
function auth($name) { return hash_hmac('sha1', $name, AUTH_SECRET); }
function return_json($data) { header('Content-Type: application/json; charset=utf-8'); echo json_encode($data, JSON_UNESCAPED_UNICODE); exit; }
function return_text($text) { header('Content-Type: text/plain; charset=utf-8'); echo $text; exit; }
function return_redirect($url) { header('Location: ' . $url); exit; }
function return_error($message) { http_response_code(404); return_json(['error' => $message]); }