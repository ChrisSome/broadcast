use `es_admin`;


# 创建赛事分类表, 自表关联
DROP TABLE IF EXISTS `admin_match_categories`;
CREATE TABLE IF NOT EXISTS `admin_match_categories`(
 `id` SMALLINT UNSIGNED AUTO_INCREMENT,
 `name` VARCHAR(32) NOT NULL COMMENT '分类名称',
 `pid` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '上级分类',
 `pname` VARCHAR(32) NOT NULL DEFAULT '' COMMENT '上级名称',
 `logo` VARCHAR(128) NOT NULL DEFAULT '' COMMENT 'logo',
 `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '是否开启0开启1关闭',
 `third_id` SMALLINT UNSIGNED NOT NULL default 0 COMMENT '三方分类id',
 `third_status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '三方状态是否开启0开启1关闭',
 `created_at` timestamp default current_timestamp,
 `updated_at` timestamp default current_timestamp,
 PRIMARY KEY (`id`),
 KEY `status_pid_cate`(`status`, `third_id`, `cate_id`) USING BTREE
)ENGINE=MyISAM default CHARSET=utf8mb4 comment='赛事分类表';

# 创建球队表
DROP TABLE IF  EXISTS `admin__match_sport_team`;
CREATE TABLE IF NOT EXISTS `admin__match_sport_team`(
  `id` SMALLINT UNSIGNED AUTO_INCREMENT,
  `logo` VARCHAR(128) NOT NULL DEFAULT '' COMMENT 'logo',
  `competition_id` SMALLINT UNSIGNED NOT NULL COMMENT '赛事id',
  `competition_name` VARCHAR(32) NOT NULL DEFAULT '' COMMENT '赛事名称',
  `name_zh` VARCHAR(64) NOT NULL COMMENT '中文名称',
  `short_name_zh` VARCHAR(64) NOT NULL COMMENT '中文简称',
  `name_zht` VARCHAR(64) NOT NULL COMMENT '粤语名称',
  `short_name_zht` VARCHAR(64) NOT NULL COMMENT '粤语简称',
  `name_cn` VARCHAR(64) NOT NULL COMMENT '英文名称',
  `short_name_cn` VARCHAR(64) NOT NULL COMMENT '英文简称',
  `national` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否国家队0是1不是',
  `foundation_time` INT NOT NULL DEFAULT 0 COMMENT '成立时间',
  `website` VARCHAR(128) NOT NULL DEFAULT '' COMMENT '网站',
  `manager_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '教练id',
  `venue_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '场馆id',
  `task_players` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '总球员数',
  `foreign_players` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '非本土总球员数',
  `national_players` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '国家队总球员数',
  `market_value` integer UNSIGNED NOT NULL DEFAULT 60 COMMENT '市值',
  `market_value_currency` VARCHAR(10) not NULL DEFAULT '' COMMENT '市值单位',
  `detail` TEXT NOT NULL COMMENT '球队详情',
  `desc` VARCHAR(255) NOT NULL COMMENT '球队简介',
  `created_at` timestamp default current_timestamp,
  `updated_at` timestamp default current_timestamp,
  `third_id` SMALLINT UNSIGNED NOT NULL default 0 COMMENT '三方id',
  PRIMARY KEY (`id`),
  KEY `cate_name_third`(`cate_id`, `name`, `third_id`)
)ENGINE=MyISAM default CHARSET=utf8mb4 comment='足球球队表';

# 创建球员表
DROP TABLE IF  EXISTS `admin__match_footbal_player`;
CREATE TABLE IF NOT EXISTS `admin__match_footbal_player`(
  `id` INT UNSIGNED AUTO_INCREMENT,
  `logo` VARCHAR(128) NOT NULL DEFAULT '' COMMENT 'logo',
  `team_id` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '球队id',
  `name_zh` VARCHAR(64) NOT NULL COMMENT '中文名称',
  `short_name_zh` VARCHAR(64) NOT NULL COMMENT '中文简称',
  `name_cn` VARCHAR(64) NOT NULL COMMENT '英文名称',
  `short_name_cn` VARCHAR(64) NOT NULL COMMENT '英文简称',
  `birthday` int  NOT NULL DEFAULT 0 COMMENT '生日',
  `weight` int UNSIGNED NOT NULL DEFAULT 80 COMMENT '身高KG',
  `height` int UNSIGNED NOT NULL DEFAULT 180 COMMENT '身高cm',
  `nationality` VARCHAR(32)  NOT NULL COMMENT '国家',
  `market_value` integer UNSIGNED NOT NULL DEFAULT 60 COMMENT '市值',
  `market_value_currency` VARCHAR(10) not NULL DEFAULT '' COMMENT '市值单位',
  `contract_until` timestamp NOT NULL  COMMENT '合同终止时间',
  `position` varchar(5) NOT NULL  DEFAULT '' COMMENT '擅长位置F前锋M中锋D后卫G守门员其他未知',
  `preferred_foot` TINYINT UNSIGNED  NOT NULL  DEFAULT 1 COMMENT '擅长脚0未知1左2右3左右',
  `detail` TEXT NOT NULL COMMENT '球员详情',
  `desc` VARCHAR(255) NOT NULL COMMENT '球员简介',
  `created_at` timestamp default current_timestamp,
  `updated_at` timestamp default current_timestamp,
  PRIMARY KEY (`id`),
  KEY `team_time`(`team_id`, `contract_until`)
)ENGINE=MyISAM default CHARSET=utf8mb4 comment='足球球员表';


# 赛事表
DROP TABLE IF EXISTS `admin_footbal_completes`;
CREATE TABLE IF NOT EXISTS `admin_footbal_completes`(
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` INT UNSIGNED NOT NULL COMMENT '分类id',
  `country_id` INT UNSIGNED NOT NULL COMMENT '国家id',
  `name_zh` VARCHAR(64) NOT NULL COMMENT '中文名称',
  `short_name_zh` VARCHAR(64) NOT NULL COMMENT '中文简称',
  `name_zht` VARCHAR(64) NOT NULL COMMENT '粤语名称',
  `short_name_zht` VARCHAR(64) NOT NULL COMMENT '粤语简称',
  `name_cn` VARCHAR(64) NOT NULL COMMENT '英文名称',
  `type` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '赛事类型0未知1联赛2杯赛3友谊赛',
  `cur_season_id` int unsigned not null default 0 comment '当前赛季Id',
  `cur_season_id` int unsigned not null default 0 comment '当前赛季Id',
  `cur_round` TINYINT unsigned not null default 0 comment '当前轮次',
  `round_count` TINYINT unsigned not null default 0 comment '总论此',
  `logo` VARCHAR(128) NOT NULL DEFAULT '' COMMENT 'logo',
  `title_holder` VARCHAR(128) NOT NULL DEFAULT '' COMMENT 'logo',


)ENGINE=MyISAM default CHARSET=utf8mb4 comment='足球赛事表';;

# 赛事表
DROP TABLE IF EXISTS  `admin_matches`;
CREATE TABLE IF NOT EXISTS `admin_matches`(
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `visting_team` VARCHAR(64) NOT NULL COMMENT '客队名称',
  `visting_id` SMALLINT UNSIGNED  NOT NULL COMMENT '客队ID',
  `home_team` VARCHAR(64) NOT NULL COMMENT '主队名称',
  `home_id` SMALLINT UNSIGNED  NOT NULL COMMENT '主队ID',
  `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '比赛状态0未开始1进行中2已结束'
  `created_at` timestamp default current_timestamp COMMENT '开赛时间',
  `done_at` timestamp default current_timestamp COMMENT '完赛时间',
  PRIMARY KEY (`id`)
)ENGINE=InnoDB default CHARSET=utf8mb4 comment='赛事表';

# 赛事直播信息, 按周分区
DROP TABLE  IF EXISTS  `admin_match_messages`;
CREATE TABLE IF NOT EXISTS `admin_match_message_texts`(
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `match_id` INT UNSIGNED NOT NULL COMMENT '比赛id',
  `message` TEXT NOT NULL COMMENT '信息',
  `created_at` DATETIME NOT NULL  ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`, `created_at`)
)ENGINE=InnoDB default CHARSET=utf8mb4 comment='赛事表'
PARTITION BY RANGE (YEARWEEK(created_at)) (
    PARTITION p202017 VALUES LESS THAN (202018),
    PARTITION p202018 VALUES LESS THAN (202019),
    PARTITION p202019 VALUES LESS THAN (202020),
    PARTITION p202020 VALUES LESS THAN (202021),
    PARTITION p202021 VALUES LESS THAN (202022),
    PARTITION p202022 VALUES LESS THAN (202023),
    PARTITION p52 VALUES LESS THAN MAXVALUE
);