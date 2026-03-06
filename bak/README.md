# meting-api

## 注

此仓库在[injahow/meting-api](https://github.com/injahow/meting-api)的基础上补充支持`type=search`参数。目前主要测试 netease 和 Tencent 音乐源。

## Descriptions

- 这是基于 [Meting](https://kkgithub.com/metowolf/Meting) 创建的 APlayer API
- 灵感源于 https://api.fczbl.vip/163/
- 部分参考 [Meting-API](https://kkgithub.com/metowolf/Meting-API)

## Build Setup

```
# 克隆仓库
$ git clone https://github.com/injahow/meting-api.git

$ cd meting-api

# 安装依赖
$ composer install

# 或者使用中国镜像
$ composer config -g repo.packagist composer https://packagist.phpcomposer.com

$ composer install
```

或者下载打包文件https://github.com/injahow/meting-api/releases

或者直接使用 Meting.php

```
// include __DIR__ . '/vendor/autoload.php';
include __DIR__ . '/src/Meting.php';
```

修改代码参数

```
<?php
// 设置API路径（可默认）
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

......
```

## Demo

API-Demo:

- https://api.injahow.cn/meting/?type=url&id=416892104
- https://api.injahow.cn/meting/?type=song&id=591321
- https://api.injahow.cn/meting/?type=playlist&id=2619366284

APlayer-Demo:

- https://injahow.github.io/meting-api/
- https://injahow.github.io/meting-api/?id=2904749230

## Thanks

- [APlayer](https://kkgithub.com/MoePlayer/APlayer)
- [Meting](https://kkgithub.com/metowolf/Meting)
- [MetingJS](https://kkgithub.com/metowolf/MetingJS)

## Requirement

PHP 5.4+ and BCMath, Curl, OpenSSL extension installed.

## License

[MIT](https://kkgithub.com/injahow/meting-api/blob/master/LICENSE) license.

Copyright (c) 2019 injahow