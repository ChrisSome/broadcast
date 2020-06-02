use es_admin;


# 帖子表
DROP TABLE IF EXISTS `admin_user_posts`;
CREATE  TABLE IF NOT EXISTS `admin_user_posts`(
 `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
 `user_id` INT UNSIGNED NOT NULL COMMENT '用户id',
 `nickname` VARCHAR(64) NOT NULL COMMENT '昵称',
 `head_photo` VARCHAR(124) NOT NULL DEFAULT '' COMMENT '用户头像',
 `title` VARCHAR(64) NOT NULL COMMENT '标题',
 `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0待审核1审核2删除',
 `is_top` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '置顶0不是',
 `content` TEXT NOT NULL COMMENT '内容',
 `remark` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '备注',
 `hit` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '浏览次数',
 `fabolus_number` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '点赞数',
 `created_at`  timestamp default current_timestamp,
 `updated_at`  timestamp default current_timestamp,
 PRIMARY KEY (`id`),
 KEY `top_status_time`(`is_top`, `status`, `created_at`) USING BTREE ,
 KEY `user_create`(`user_id`, `status`, `created_at`) USING BTREE ,
 KEY `time`(`created_at`)
)engine=InnoDB default CHARSET=utf8mb4 comment='帖子表';

# 帖子操作表
DROP TABLE IF EXISTS `admin_post_operates`;
CREATE TABLE IF NOT EXISTS `admin_post_operates`(
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL COMMENT '用户id',
  `nickname` varchar(64) NOT NULL COMMENT '用户昵称',
  `mobile` CHAR(13) NOT NULL COMMENT '手机号',
  `action_type` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '行为1，点赞， 2收藏， 3， 举报',
  `post_id` INT UNSIGNED NOT NULL COMMENT '帖子id',
  `post_title` VARCHAR(64) NOT NULL COMMENT '帖子标题',
  `content` VARCHAR(128) NOT NULL DEFAULT '' COMMENT '举报原因',
  `remark` VARCHAR(128) NOT NULL DEFAULT '' COMMENT '备注',
  `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0待审核1审核2删除',
  `created_at`  timestamp default current_timestamp,
  `updated_at`  timestamp default current_timestamp,
  PRIMARY KEY `id`(`id`),
  KEY `user_post_type`(`user_id`, `post_id`, `action_type`) USING BTREE
)engine=InnoDB default CHARSET=utf8mb4 comment='帖子操作表';

# 帖子评论表
DROP TABLE IF EXISTS `admin_user_post_comments`;
CREATE TABLE IF NOT EXISTS `admin_user_post_comments`(
  `id` INT UNSIGNED AUTO_INCREMENT,
  `post_id` INT UNSIGNED NOT NULL COMMENT '帖子id',
  `post_title` VARCHAR(64) NOT NULL COMMENT '帖子标题',
  `user_id` INT UNSIGNED NOT NULL COMMENT '用户id',
  `mobile` CHAR(13) NOT NULL COMMENT '手机号',
  `nickname` VARCHAR(64) NOT NULL COMMENT '昵称',
  `photo` VARCHAR(128) NOT NULL COMMENT '头像',
  `content` VARCHAR(90) NOT NULL COMMENT '评论内容',
  `parent_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '上条id',
  `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0待审核1审核2删除',
  `next_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '下级评论条数',
  `fabolus_number` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '点赞数',
  `parent_user_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '上级用户id',
  `parent_user_nickname` VARCHAR(128) NOT NULL DEFAULT '' COMMENT '上级用户昵称',
  `path` VARCHAR(1024) NOT NULL DEFAULT '' COMMENT '层级',
  `created_at`  timestamp default current_timestamp,
  `updated_at`  timestamp default current_timestamp,
  PRIMARY KEY  `id`(`id`),
  KEY `user_create`(`user_id`, `created_at`) USING BTREE,
  KEY `parent_create`(`parent_id`, `created_at`) USING BTREE
)engine=InnoDB default CHARSET=utf8mb4 comment='帖子评论表';
