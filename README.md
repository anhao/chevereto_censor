# chevereto_censor
Chevereto 鉴黄
支持：
 - 百度AI鉴黄
 - 腾讯AI 鉴黄
 - moderatecontent
 - Sightengine
 
 # 使用方法
 > 需要支持 `composer` php版本最好 php7 以上
 
## 安装 软件包
 
`composer require alone88/chevereto_censor`

## 覆盖 Chevereto 文件
1. 把 `chevereto` 目录下的 `route.api.php` `route.json.php` `route.dashboard.php`
上传到 `Chevereto` 应用下 `app/routes/overrides/` 目录下

2. 把 `class.censor.php` 上传到 `app/lib/classes/` 目录下
3. 把` zh-cn.po `上传到 `app/content/languages/overrides/` 目录下
4. 根据自己的 `chevereto` 版本选择 `dashboard` 文件，付费版在 `dashboard-pro` 目录下
免费版在 `dashboard-free` 目录下，选择好文件，上传到 `app/themes/Peafowl/overrides/views`
目录下

## 最后插入数据库新增语句
选择 `chevereto` 目录下的 `insert.sql` 文件插入你的数据库
注意里面的表名和你的系统一样的

## 后台界面
可以在后台设置响应的参数
![后台界面](https://ae01.alicdn.com/kf/Hba059659f78d428d9f46b14d3c3b3624Y.png)