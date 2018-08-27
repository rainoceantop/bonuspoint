-- 使用数据库
USE bonus_point;

-- 打开mysql事件
SET @global.event_scheduler = 1;

-- 删除存储过程或事件
DROP PROCEDURE IF EXISTS ORDER_UPDATE;
DROP PROCEDURE IF EXISTS RETURN_DAYS_UPDATE;
DROP PROCEDURE IF EXISTS CANCEL_ORDER;
DROP EVENT IF EXISTS RETURN_DAYS_CHECK;

-- 存储过程：根据模式操作积分
CREATE PROCEDURE ORDER_UPDATE(
    IN _uid INT,
    IN _tid INT, -- 订单类型的id
    IN _name varchar(150),
    IN _type enum('+','-'), -- 表示增加或是减少订单
    IN _price INT, -- 商品的价格
    IN _points INT, -- 商品的积分
    IN _mode TINYINT, -- 哪种模式，0代表立即，1代表两步 
    IN _return_days TINYINT -- 0代表已过退货期，大于0代表剩余天数
)
BEGIN
    DECLARE e TINYINT DEFAULT 0;
    DECLARE _oid INT UNSIGNED;
    DECLARE _bfor INT UNSIGNED;
    DECLARE _ater INT UNSIGNED;
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION SET e = 1;

    -- 判断模式，如果是立即模式则退货期限为0
    IF _mode = 0 THEN 
        SET _return_days = -1;-- 立即到帐模式，退货期限为0
    END IF;

    START TRANSACTION;
    INSERT INTO orders(uid, tid, name, type, price, points, mode, return_days) VALUE(_uid, _tid, _name, _type, _price, _points, _mode, _return_days);
    SET _oid = (SELECT id FROM orders ORDER BY id DESC LIMIT 0,1);
    SET _bfor = (SELECT bpoint FROM accounts WHERE uid = _uid LIMIT 0,1);

    IF _mode = 0 THEN 
        IF (_type = '+') THEN
            SET _ater = (_bfor + _points);
        ELSE
            SET _ater = (_bfor - _points);
        END IF;
        UPDATE accounts SET bpoint = _ater WHERE uid = _uid; -- 立即更新用户积分
    ELSE
        SET _ater = _bfor; -- 两步模式，操作前后积分不变
    END IF;

    INSERT INTO changes(uid, oid, bfor, volume, ater) VALUE(_uid, _oid, _bfor, _points, _ater);
    SELECT e;
    IF e = 1 THEN
        ROLLBACK;
    ELSE
        COMMIT;
    END IF;
END;

-- 存储过程，取消订单
CREATE PROCEDURE CANCEL_ORDER(
    IN _uid INT,
    IN _oid INT
)
BEGIN
    DECLARE e TINYINT DEFAULT 0;
    DECLARE _o_uid TINYINT;
    DECLARE _return_days TINYINT;
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION SET e = 1;
    -- 获取用户订单，如果退货期限还有则将退货期限设置-1
    SELECT uid, return_days INTO _o_uid, _return_days FROM orders WHERE id = _oid;
    IF (_return_days > 0 && _uid = _o_uid) THEN
        UPDATE orders SET return_days = -2, tid = 4 WHERE id = _oid;
    ELSE
        SET e = 1;
    END IF;
    SELECT e;
END;

-- 存储过程，每天更新退货期限
CREATE PROCEDURE RETURN_DAYS_UPDATE()
BEGIN
    DECLARE e TINYINT DEFAULT 0;
    DECLARE adone TINYINT DEFAULT 0;
    DECLARE odone TINYINT DEFAULT 0;
    DECLARE _type enum('+', '-');
    DECLARE _points INT UNSIGNED;
    DECLARE _uid INT UNSIGNED;
    DECLARE _bpoint INT UNSIGNED;
    DECLARE _ater INT UNSIGNED;
    DECLARE _oid INT UNSIGNED;
    DECLARE _accounts CURSOR FOR
        SELECT DISTINCT a.uid, a.bpoint FROM accounts a, orders o WHERE a.uid = o.uid AND o.return_days = 0;
    DECLARE CONTINUE HANDLER FOR SQLSTATE '02000' SET adone = 1;
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION SET e = 1;

    START TRANSACTION;
    -- 将所有退货期限大于0的都减1
    UPDATE orders SET return_days = return_days - 1 WHERE mode = 1 AND return_days > 0;
    -- 将所有等于退货期等于0的更新积分并将其设置为-1表示已经过了退货期
    OPEN _accounts;
        account_loop:LOOP
            FETCH _accounts INTO _uid, _bpoint;
            IF (adone = 1) THEN
                LEAVE account_loop;
            END IF;
            SET odone = 0;
                BEGIN
                    DECLARE _orders CURSOR FOR
                        -- 获取该用户的订单的模式及积分
                        SELECT id, type, points FROM orders WHERE uid = _uid AND return_days = 0;
                    DECLARE CONTINUE HANDLER FOR SQLSTATE '02000' SET odone = 1;
                    OPEN _orders;
                    order_loop:LOOP
                        FETCH _orders INTO _oid, _type, _points;
                        IF (odone = 1) THEN
                            LEAVE order_loop;
                        END IF;
                        UPDATE orders SET return_days = -1 WHERE id = _oid;
                        -- 根据模式增减积分
                        IF (_type = '+') THEN
                            SET _ater = _bpoint + _points;
                        ELSE
                            SET _ater = _bpoint - _points;
                        END IF;
                        UPDATE accounts SET bpoint = _ater WHERE uid = _uid;
                        INSERT INTO changes(uid, oid, bfor, volume, ater) VALUE(_uid, _oid, _bpoint, _points, _ater);
                    END LOOP;
                    CLOSE _orders;
                END;
        END LOOP;
    CLOSE _accounts;
    SELECT e;
    IF e = 1 THEN
        ROLLBACK;
    ELSE
        COMMIT;
    END IF;
END;

-- 定时器，每天检查退货期限，如果为0则将积分更新给用户
CREATE EVENT RETURN_DAYS_CHECK
ON SCHEDULE EVERY 1 DAY
STARTS now()
DO CALL RETURN_DAYS_UPDATE();