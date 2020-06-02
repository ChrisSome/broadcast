# 创建数据库
create database if not exists es_admin charset utf8mb4;

use es_admin;

# 后台管理员
create table if not exists `admin_auth` (
	`id` int(10) unsigned not null AUTO_INCREMENT,
	`uname` varchar(20) not null comment '用户名',
	`pwd` text not null comment '密码',
	`encry` char(6) not null comment '加密串',
	`role_id` int(10) unsigned not null comment '组id',
	`display_name` varchar(100) default '' comment '显示用户名',
	`logined_at`  datetime comment '最近登陆时间',
	`created_at` timestamp null default current_timestamp,
	`status` tinyint(1) default 1 comment '状态 0 启用 1禁用 ',
  	`deleted` tinyint(1) default 0 ,
	PRIMARY key(`id`),
	UNIQUE KEY(`uname`)
) engine=InnoDB default CHARSET=utf8mb4 COMMENT='后台管理员';

INSERT INTO `admin_auth`(`id`, `uname`, `pwd`, `encry`, `role_id`, `display_name`, `status`, `deleted`) VALUES 
(1, 'admin', '617d19b72e725a05addf91d5430d240f', 'XK.?}<', 1, 'jmz', 1, 0)
,(2, 'jmz', '76f754fabe97d1e1e451fe531df5160b', 'M@q}DS', 2, 'caiwu 123', 1, 0);


# 组
create table if not exists `admin_role` (
	`id` int(10) unsigned not null AUTO_INCREMENT,
	`name` varchar(50) not null comment '组名',
	`detail` varchar(200) not null comment '简单描述',
	`rules_checked` text  comment 'layui 树形选中的checked',
	`rules` text  comment '权限列表 所有打勾的',
	`pid` int(10) unsigned default 0 comment '上级部门',
	`created_at` timestamp default current_timestamp,
	PRIMARY key(`id`)
) engine =InnoDB default charset=utf8mb4 comment= '组名';

INSERT INTO `admin_role`(`id`, `name`, `detail`, `rules_checked`, `rules`) VALUES 
(1, '超级管理员', '网站建设者', '5,6,7,8,9,10,11,12,17,13,14,15,16', '1,2,5,6,7,8,3,9,10,11,12,17,4,13,14,15,16')
,(2,'测试','测试小角色','5,6,7,8,9,10,11,12,17,13,14,15,16,19','1,2,5,6,7,8,3,9,10,11,12,17,4,13,14,15,16,18,19');

# 权限
CREATE TABLE if not exists `admin_rule` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '自增ID',
  `name` varchar(128) NOT NULL DEFAULT '' COMMENT '名称',
  `node` varchar(50) default '' comment '节点',
  `status` tinyint(2) unsigned NOT NULL DEFAULT '1' COMMENT '1 启用; 0 禁用',
  `pid` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '父级ID',
  `created_at` timestamp default current_timestamp,
  PRIMARY KEY (`id`),
  KEY node(`node`),
  KEY `status_node` (`status`,`node`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='权限点和菜单列表';

INSERT INTO `admin_rule`(`id`, `name`, `node`, `status`, `pid`) VALUES (1, '管理用户', 'auth', 1, 0);
INSERT INTO `admin_rule`(`id`, `name`, `node`, `status`, `pid`) VALUES (2, '后台管理员', 'auth.auth', 1, 1);
INSERT INTO `admin_rule`(`id`, `name`, `node`, `status`, `pid`) VALUES (3, '角色管理', 'auth.role', 1, 1);
INSERT INTO `admin_rule`(`id`, `name`, `node`, `status`, `pid`) VALUES (4, '权限管理', 'auth.rule', 1, 1);
INSERT INTO `admin_rule`(`id`, `name`, `node`, `status`, `pid`) VALUES (5, '查看管理列表', 'auth.auth.view', 1, 2);
INSERT INTO `admin_rule`(`id`, `name`, `node`, `status`, `pid`) VALUES (6, '添加管理员', 'auth.auth.add', 1, 2);
INSERT INTO `admin_rule`(`id`, `name`, `node`, `status`, `pid`) VALUES (7, '修改管理员', 'auth.auth.set', 1, 2);
INSERT INTO `admin_rule`(`id`, `name`, `node`, `status`, `pid`) VALUES (8, '删除管理员', 'auth.auth.del', 1, 2);
INSERT INTO `admin_rule`(`id`, `name`, `node`, `status`, `pid`) VALUES (9, '查看角色', 'auth.role.view', 1, 3);
INSERT INTO `admin_rule`(`id`, `name`, `node`, `status`, `pid`) VALUES (10, '增加角色', 'auth.role.add', 1, 3);
INSERT INTO `admin_rule`(`id`, `name`, `node`, `status`, `pid`) VALUES (11, '修改角色', 'auth.role.set', 1, 3);
INSERT INTO `admin_rule`(`id`, `name`, `node`, `status`, `pid`) VALUES (12, '删除角色', 'auth.role.del', 1, 3);
INSERT INTO `admin_rule`(`id`, `name`, `node`, `status`, `pid`) VALUES (13, '查看权限', 'auth.rule.view', 1, 4);
INSERT INTO `admin_rule`(`id`, `name`, `node`, `status`, `pid`) VALUES (14, '增加权限', 'auth.rule.add', 1, 4);
INSERT INTO `admin_rule`(`id`, `name`, `node`, `status`, `pid`) VALUES (15, '修改权限', 'auth.rule.set', 1, 4);
INSERT INTO `admin_rule`(`id`, `name`, `node`, `status`, `pid`) VALUES (16, '删除权限', 'auth.rule.del', 1, 4);
INSERT INTO `admin_rule`(`id`, `name`, `node`, `status`, `pid`) VALUES (17, '变更权限', 'auth.role.rule', 1, 3);
INSERT INTO `admin_rule`(`id`, `name`, `node`, `status`, `pid`) VALUES (18, '主页', 'index', 1, 0);
INSERT INTO `admin_rule`(`id`, `name`, `node`, `status`, `pid`) VALUES (19, '登录日志', 'index.login.log', 1, 18);

# 操作日志记录
CREATE TABLE if not exists `admin_log` (
	`id` int(10) unsigned not null auto_increment,
	`url` varchar(50) not null DEFAULT '' comment '操作url',
	`data` text comment '信息',
	`uid` int(10) comment '操作人',
	`created_at` timestamp default current_timestamp,
	PRIMARY KEY(`id`)
) engine=MyISAM default CHARSET=utf8mb4 comment='操作记录表';

# 登录日志记录
CREATE TABLE if not exists `admin_login_log`(
	`id` int(10) unsigned not null auto_increment,
	`uname` varchar(20) comment '登录人',
	`status` tinyint(1) default '0' comment '是否登录 1 登录成功，0失败',
	`created_at` timestamp default current_timestamp,
	PRIMARY KEY(`id`)
)engine=MyISAM default CHARSET=utf8mb4 comment='登录日志记录表';

# 创建直播源列表
CREATE TABLE IF NOT EXISTS `admin_play`(
    `id` TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(32) NOT NULL DEFAULT '' COMMENT '主播名称',
    `url` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '主播网址',
    `func` VARCHAR(20) NOT NULL COMMENT '接入方法，需后端开发配置',
    `cover_img` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '封面图片',
    `status` tinyint(2) unsigned NOT NULL DEFAULT '1' COMMENT '1 启用; 0 禁用',
    `uid` int(10) comment '操作人',
    `created_at` timestamp default current_timestamp,
    PRIMARY KEY(`id`)
)engine=MyISAM default CHARSET=utf8mb4 comment='直播源列表';

# 创建用户表
DROP TABLE IF EXISTS `admin_user`;
CREATE TABLE IF NOT EXISTS `admin_user`(
 `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
 `mobile` VARCHAR(32) NOT NULL DEFAULT '' COMMENT '手机号',
 `nickname` VARCHAR(32) NOT NULL DEFAULT '' COMMENT '昵称',
 `password_hash` VARCHAR(128) NOT NULL DEFAULT '' COMMENT '密码',
 `photo` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '头像',
 `wx_photo` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '微信头像',
 `wx_name` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '微信昵称',
 `third_wx_openid` VARCHAR(128) NOT NULL DEFAULT '' COMMENT '微信id',
 `sign_at` timestamp comment '最后登陆时间',
 `status` tinyint(2) unsigned NOT NULL DEFAULT '1' COMMENT '1 启用; 0 禁用',
 `is_online` tinyint(2) unsigned NOT NULL DEFAULT 0 COMMENT '1 在线; 0 不在线',
 `created_at` timestamp default current_timestamp,
 `updated_at` timestamp default current_timestamp,
 PRIMARY KEY(`id`),
 UNIQUE KEY `mobile`(`mobile`),
 KEY `created_at`(`created_at`),
 KEY `sign_at`(`sign_at`)
)engine=InnoDB default CHARSET=utf8mb4 comment='用户表';

# 系统配置表
CREATE TABLE IF NOT EXISTS `admin_sys_setting`(
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sys_key` VARCHAR(24) NOT NULL  COMMENT '系统配置key',
  `sys_value` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '配置项值',
  `created_at` timestamp default current_timestamp,
  `updated_at` timestamp default current_timestamp,
  PRIMARY KEY(`id`),
  UNIQUE KEY `sys_key`(`sys_key`)
)engine=InnoDB default CHARSET=utf8mb4 comment='系统配置表';

# 意见表
DROP TABLE IF EXISTS `admin_user_options`;
CREATE TABLE IF NOT EXISTS `admin_user_options`(
 `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
 `user_id` INT UNSIGNED NOT NULL COMMENT '用户id',
 `nickname` varchar(64) NOT NULL COMMENT '昵称',
 `phone` VARCHAR(13) NOT NULL COMMENT '手机号',
 `content` TEXT NOT NULL COMMENT '反馈内容',
 `reply` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '回复内容',
 `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0待审核1已回复',
 `admin_id` INT UNSIGNED NOT NULL DEFAULT  0 COMMENT '回复人id',
 `admin_name` VARCHAR(64) NOT NULL default '' COMMENT '管理员',
 `created_at` timestamp default current_timestamp,
 `updated_at` timestamp default current_timestamp,
 PRIMARY KEY (`id`),
 KEY `user_created`(`user_id`, `created_at`) USING btree
)engine=InnoDB default CHARSET=utf8mb4 comment='意见表';


# 创建消息表
DROP TABLE IF EXISTS `admin_messages`;
CREATE TABLE IF NOT EXISTS `admin_messages`(
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `type` varchar(10) NOT NULL DEFAULT '' COMMENT '消息类型广播',
  `sender_user_id` INT UNSIGNED NOT NULL COMMENT '来源用户id',
  `sender_mobile` char(13) NOT NULL COMMENT '发送手机号',
  `sender_photo` varchar(128) NOT NULL COMMENT '发送者头像',
  `sender_nickname` VARCHAR(64) NOT NULL COMMENT '来源昵称',
  `match_id` INT UNSIGNED NOT NULL COMMENT '比赛id',
  `content` TEXT NOT NULL  COMMENT '消息实体',
  `with_message_id` INT UNSIGNED  NOT NULL  DEFAULT 0 COMMENT '@messageId',
  `created_at` timestamp default current_timestamp,
  PRIMARY KEY(`id`),
  KEY `group_username`(`match_id`, `sender_user_id`),
  KEY `group_time`(`match_id`, `created_at`)
)engine=InnoDB default CHARSET=utf8mb4 comment='聊天记录表';

# 创建群组表 (可能不需要， 只需要记录对应的直播源id即可)


# 创建异常表，方便排错
DROP TABLE IF EXISTS `exceptions`;
CREATE TABLE IF NOT EXISTS `exceptions`(
 `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
 `path` VARCHAR(64) NOT NULL COMMENT '路径',
 `message` VARCHAR(255) NOT NULL COMMENT '消息',
 `detail` TINYTEXT NOT NULL,
 `created_at` timestamp default current_timestamp,
 PRIMARY KEY(`id`)
)engine=MyISAM default CHARSET=utf8mb4 comment='消息表';

# 创建用户登陆日志表
/*DROP TABLE IF EXISTS `admin_user_login_log`;
CREATE TABLE IF NOT EXISTS `admin_user_login_log`(
 `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
 `uid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户id',
 `username` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '登陆用户',
 `created_at` timestamp default current_timestamp COMMENT '登陆时间',
 `drop_at` timestamp default current_timestamp COMMENT '下线时间'

)engine=InnoDB default CHARSET=utf8mb4 comment='用户登陆日志表';*/


# 创建系统消息类型
DROP TABLE IF EXISTS `admin_system_message_category`;
CREATE TABLE IF NOT EXISTS `admin_system_message_category`(
  `id` TINYINT UNSIGNED AUTO_INCREMENT,
  `name` varchar(32) NOT NULL COMMENT '分类名称',
  `pid` tinyint UNSIGNED NOT NULL DEFAULT 0 COMMENT '上级id',
  `pname` varchar(32) NOT NULL DEFAULT '' COMMENT '上级名称',
  `admin_id` INT UNSIGNED NOT NULL DEFAULT  0 COMMENT '管理员id',
  `admin_name` VARCHAR(64) NOT NULL default '' COMMENT '管理员',
  `created_at` timestamp default current_timestamp,
  `updated_at` timestamp default current_timestamp,
  PRIMARY KEY(`id`)
)engine=InnoDB default CHARSET=utf8mb4 comment='系统消息类型';


# 创建系统消息列表
DROP TABLE IF EXISTS `admin_system_message_lists`;
CREATE TABLE IF NOT EXISTS `admin_system_message_lists`(
 `id` TINYINT UNSIGNED AUTO_INCREMENT,
 `cate_name` varchar(32) NOT NULL COMMENT '分类名称',
 `cate_id` tinyint UNSIGNED NOT NULL  COMMENT '分类id',
 `title` VARCHAR(64) NOT NULL COMMENT '标题',
 `content` TEXT NOT NULL COMMENT '消息内容',
 `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0待审核1已公布2已删除',
 `admin_id` INT UNSIGNED NOT NULL DEFAULT  0 COMMENT '管理员id',
 `admin_name` VARCHAR(64) NOT NULL default '' COMMENT '管理员',
 `created_at` timestamp default current_timestamp,
 `updated_at` timestamp default current_timestamp,
  PRIMARY KEY(`id`),
  KEY `cate_status_time`(`cate_id`, `status`, `created_at`)
)engine=InnoDB default CHARSET=utf8mb4 comment='系统消息表';

# 消息用户已读
DROP TABLE IF EXISTS `admin_user_read_records`;
CREATE TABLE IF NOT EXISTS `admin_user_read_records`(
 `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
 `message_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '消息id',
 `message_title` VARCHAR(64) NOT NULL COMMENT '标题',
 `user_id` INT UNSIGNED NOT NULL COMMENT '用户id',
 `mobile` VARCHAR(13) NOT NULL COMMENT '手机号',
 `created_at`  timestamp default current_timestamp,
 PRIMARY KEY(`id`),
 unique  key `user_message`(`user_id`, `message_id`) USING btree
)engine=InnoDB default CHARSET=utf8mb4 comment='消息用户已读表';

# 短信验证码表
DROP TABLE IF EXISTS `admin_user_phonecode`;
CREATE TABLE IF NOT EXISTS `admin_user_phonecode`(
 `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
 `mobile` VARCHAR(13) NOT NULL COMMENT '手机号',
 `code` CHAR(6) NOT NULL COMMENT '验证码',
 `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0待审核1已使用',
 `created_at`  timestamp default current_timestamp,
 PRIMARY KEY (`id`),
 KEY `creatd_at`(`created_at`)
)engine=InnoDB default CHARSET=utf8mb4 comment='短信验证码表';

