-- 如果存在数据库，删除
DROP DATABASE IF EXISTS bonus_point;

-- 创建数据库
CREATE DATABASE bonus_point;

-- 设置数据库编码
ALTER DATABASE bonus_point CHARACTER SET utf8;

-- 选择数据库
USE bonus_point;

-- 用户表
CREATE TABLE users
(
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(50) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT now(),
    last_online TIMESTAMP NOT NULL DEFAULT now(),
    PRIMARY KEY(id)
);

-- 账户表
CREATE TABLE accounts
(
    uid INT UNSIGNED NOT NULL,
    name VARCHAR(30) NOT NULL UNIQUE,
    avatar VARCHAR(200) NOT NULL DEFAULT "imgs/user_default.jpg",
    bpoint INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY(uid),
    FOREIGN KEY(uid) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
);

-- 类型表
CREATE TABLE types
(
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(200) NOT NULL,
    PRIMARY KEY(id)
);

-- 订单表
CREATE TABLE orders
(
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    uid INT UNSIGNED NOT NULL,
    tid INT UNSIGNED NOT NULL, -- 订单类型的id
    name varchar(150) NOT NULL,
    type enum('+','-') NOT NULL, -- 表示增加或是减少订单
    price INT UNSIGNED NOT NULL, -- 商品的价格
    points INT UNSIGNED NOT NULL, -- 商品的积分
    at TIMESTAMP NOT NULL DEFAULT now(),
    mode TINYINT NOT NULL DEFAULT 0, -- 默认0，立即到帐模式
    return_days TINYINT UNSIGNED NOT NULL DEFAULT 0, -- 默认0，表示过了退货期
    PRIMARY KEY(id),
    FOREIGN KEY(uid) REFERENCES users(id),    
    FOREIGN KEY(tid) REFERENCES types(id)
);

-- 变化表
CREATE TABLE changes
(
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    uid INT UNSIGNED NOT NULL,
    oid INT UNSIGNED NOT NULL,
    bfor INT UNSIGNED NOT NULL,
    volume INT UNSIGNED NOT NULL,
    ater INT UNSIGNED NOT NULL,
    at TIMESTAMP NOT NULL DEFAULT now(),
    PRIMARY KEY(id),
    FOREIGN KEY(uid) REFERENCES users(id),    
    FOREIGN KEY(oid) REFERENCES orders(id)
);