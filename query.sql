use bonus_point;

delete from users;
insert into users(username, password) value('hello', 'world');
delete from accounts;
insert into accounts(uid, name) value((select id from users limit 1), 'david');
delete from types;
insert into types(name) values('签到'),('双11活动'),('购买商品'),('退货');
select * from types;
