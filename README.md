![](https://img.shields.io/badge/version-v0.0.0.9-red.svg)
![](https://img.shields.io/badge/php-%3E=7.1-orange.svg)
![](https://img.shields.io/badge/swoole-%3E=4.0-blue.svg)
![](https://img.shields.io/badge/must-mongodb.so-yellow.svg)
![](https://img.shields.io/badge/must-tideways.so-yellow.svg)



# 简介
本项目基于github上的swoft开源项目进行组件开发，扩展了一个swoft-tideways组件,
tideways 是一个PHP性能被动分析工具，对php7支持良好，并且是非侵入式的监控
提供了火焰图，调用图和调用的完善记录

# 环境强制要求

1. 必须PHP 7.1 +
2. swoole版本必须大于等于4.0,并且按照swoft官方要求的要求安装
3. mongodb.so
4. tideaways.so, [安装方法可点击这里，官方文档非常详细](https://tideways.io/profiler/docs/setup/installation)

# 使用步骤

## 1.php.ini文件配置
```php
   [mongodb]
   extension=mongodb.so
   [tideways]
   extension=tideways.so
   ;不需要自动加载，在程序中控制就行
   tideways.auto_prepend_library=0
   ;频率设置为100，在程序调用时能改
   tideways.sample_rate=100
```


## 2.安装中文版的xhgui
```php
   git clone https://github.com/laynefyc/xhgui-branch.git
   cd xhgui-branch
   php install.php
```

## 3.mongodb服务端增加索引 (xhprof是我们使用的库名,可根据需要变更)
```php
    $ mongo
    > use xhprof
    > db.results.ensureIndex( { 'meta.SERVER.REQUEST_TIME' : -1 } )
    > db.results.ensureIndex( { 'profile.main().wt' : -1 } )
    > db.results.ensureIndex( { 'profile.main().mu' : -1 } )
    > db.results.ensureIndex( { 'profile.main().cpu' : -1 } )
    > db.results.ensureIndex( { 'meta.url' : 1 } )
```

## 4.增加一个xhgui的nginx配置文件,root 指向我们刚刚安装的xhgui-branch下面的webroot目录
#####  注：xhgui支持php56,php7-fpm,需要启动一个fpm
#####  注：若是在本地配置，记得配置hosts文件
#####  注：若报错cache目录不可写，请给cache目录权限修改为777
```php
    server {
        listen       80;
        server_name  local-xhgui.xxxx.com;
        root  /apps/webroot/production/xhgui-branch/webroot;
    
        location / {
            index  index.php;
            if (!-e $request_filename) {
                rewrite . /index.php last;
            }
        }
    
        location ~ \.php$ {
            fastcgi_pass   127.0.0.1:9001;
            fastcgi_index  index.php;
            fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
            include        fastcgi_params;
        }
    }

```
## 5.给你的swoft项目引入组件
```php
    composer require extraswoft/tideways
```

## 6.在config/beans 下面添加一个tideways.php文件
```php
    <?php
    use ExtraSwoft\Tideways\Middleware\TidewaysMiddleware;
    
    return [
        'ExtraSwoft\\Tideways\\Middleware\\TidewaysMiddleware' => [
            'class' => TidewaysMiddleware::class,
            'root' => '${config.tideways.root}',
            'start' => '${config.tideways.start}',
            'host' => '${config.tideways.host}',
            'db' => '${config.tideways.db}',
        ]
    ];
```

## 7.在config/properties/app.php 文件中添加如下信息
```php
   'tideways' => [
           'root' => env('TIDEWAYS_ROOT'),
           'start' => env('TIDEWAYS_START'),
           'host' => env('TIDEWAYS_DB_HOST'),
           'db' => env('TIDEWAYS_DB_DB'),
       ] 
```

## 8.在.env 中添加如下信息
##### TIDEWAYS_ROOT 指向你安装的xhgui的路径
##### TIDEWAYS_START 确定你当前环境是否开启
##### TIDEWAYS_DB_HOST 配置mongodb
##### TIDEWAYS_DB_DB 使用的库

```php
    #tideways
    TIDEWAYS_ROOT=/apps/webroot/production/xhgui-branch
    TIDEWAYS_START=true
    TIDEWAYS_DB_HOST=mongodb://127.0.0.1:27017
    TIDEWAYS_DB_DB=xhprof
```

## 9.分析采样率的修改

#### 在xhgui的config/config.default.php中，可设置采样命中次数
具体可参照下面例子

```php
'profiler.enable' => function() {
    // url 中包含debug=1则百分百捕获
    if(!empty($_GET['debug'])){
        return True;
    }else{
        // 1%采样
        return rand(1, 100) === 42;
    }
}
```
```php
return rand(1, 100) === 42; 
为1%的采样率，改成return True;则标识每次都采样
```


# 效果图
![image](https://github.com/masixun71/swoft-tideways/blob/master/resource/tideways.png?raw=true)


# 问题
1.现在tideways提供了sql的分析，但是mysql使用的是swoole的mysql客户端，无法记录


