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