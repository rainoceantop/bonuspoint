USE bonus_point;

DROP PROCEDURE IF EXISTS IMMEDIATELY;

CREATE PROCEDURE IMMEDIATELY(
    IN _uid INT,
    IN _tid INT, -- 订单类型的id
    IN _name varchar(150),
    IN _type enum('+','-'), -- 表示增加或是减少订单
    IN _price INT, -- 商品的价格
    IN _points INT -- 商品的积分
)
BEGIN
    DECLARE e TINYINT DEFAULT 0;
    DECLARE _oid INT;
    DECLARE _bfor INT;
    DECLARE _ater INT;
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION SET e = 1;

    START TRANSACTION;
    INSERT INTO orders(uid, tid, name, type, price, points) VALUE(_uid, _tid, _name, _type, _price, _points);
    SET _oid = (SELECT id FROM orders ORDER BY id DESC LIMIT 0,1);
    SET _bfor = (SELECT bpoint FROM accounts where uid = _uid LIMIT 0,1);

    IF (_type = '+') THEN
        SET _ater = (_bfor + _points);
    ELSE
        SET _ater = (_bfor - _points);
    END IF;

    UPDATE accounts SET bpoint = _ater where uid = _uid;

    INSERT INTO changes(uid, oid, bfor, volume, ater) VALUE(_uid, _oid, _bfor, _points, _ater);
    SELECT e;
    IF e = 1 THEN
        ROLLBACK;
    ELSE
        COMMIT;
    END IF;
END;