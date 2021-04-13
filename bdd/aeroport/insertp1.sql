insert into avions values(324, 'airbus320',10000);
insert into avions values(532, 'bac180',1000);
insert into avions values(3031, 'boeing747', 20000);
insert into avions values(4041, 'airbus340', 20000);




insert into clients values(14511, 'Durand','Daniel', 'Snecma');
insert into clients values(27511, 'Lambert', 'Sophie', 'Bouygues');
insert into clients values(16012, 'Grimaldi', 'Jean', 'Matra');
insert into clients values(16701, 'Wilson', 'Marc', 'Alcatel');
insert into clients values(25806, 'Durand', 'Sylvie', 'CEA');
insert into clients values(45200, 'Pabon', 'Mario', 'Bouygues');
insert into clients values(25400, 'Voisin', 'Frederic', 'Bouygues');
insert into clients values(31200, 'Tronche', 'Christophe', 'Matra');
insert into clients values(55555, 'Totou', 'Paul', 'Loreal');
insert into clients values(41500, 'Colsson', NULL, 'Alcatel' );
insert into clients values(42200, 'Grandos', NULL, 'LMET' );



-- insert into employes values(16507, 'Dupont', 350);
-- insert into employes values(16302, 'Durand', 500);
-- insert into employes values(15504, 'Pierre', 350);
-- insert into employes values(27006, 'Martin', 200);
-- insert into employes values(15809, 'Courrier', 500);
-- insert into employes values(26010, 'Dupont', 600);
-- insert into employes values(13456, 'Blanc', 125);
-- insert into employes values(13421, 'LeBihan', 110);
-- insert into employes values(13277, 'Waller', 120);
-- insert into employes values(13458, 'Safar', NULL);
-- insert into employes values(43200, 'Delmel', NULL);
-- insert into employes values(32500, 'Delmel', NULL);
-- insert into employes values(43210, 'Rabbit', 800);

insert into employes values (16507, 'Dupont','01-FEB-60', '01-FEB-82', '01-JAN-02', 350, 100); 
insert into employes values (16302, 'Durand','01-JUN-50', '01-SEP-70', '01-JAN-00', 500,  50); 
insert into employes values (15504, 'Pierre','06-AUG-80', '01-OCT-00', '01-OCT-01', 350,  NULL);  
insert into employes values (27006, 'Martin','26-MAY-78', '01-DEC-99', '01-DEC-02', 200,  10);
insert into employes values (15809, 'Courrier', '27-JUN-66', '01-MAR-00', '01-MAR-01', 500,  NULL);
insert into employes values (26010, 'Dupont','10-OCT-80', '01-OCT-00', '01-OCT-02', 600,  50);
insert into employes values (13456, 'Blanc','10-OCT-78', '01-OCT-99', '01-OCT-01', 125,  30); 
insert into employes values (13421, 'LeBihan', '12-DEC-75', '01-JAN-96', '01-JAN-03', 110, 100);
insert into employes values (13277, 'Waller','12-DEC-75', '01-FEB-97', '01-JAN-02', 120,  80); 
insert into employes values (13458, 'Safar','20-DEC-45', '01-JAN-65', '01-JAN-03', NULL, 300); 
insert into employes values (43200, 'Delmel','01-JAN-56', '01-JAN-80', '01-MAR-02', NULL, NULL);
insert into employes values (32500, 'Delmel','15-MAR-80', '01-APR-00', '01-APR-01', NULL, 40); 
insert into employes values (43210, 'Rabbit','20-OCT-46', '01-JAN-65', '01-JAN-03', 800, 100);
insert into employes values (44444, 'Ajout','20-OCT-46', '20-OCT-02', '20-OCT-03', 800,  10); 

insert into qualif values(26010, 324);
insert into qualif values(26010, 532);
insert into qualif values(26010, 3031);
insert into qualif values(26010, 4041);
insert into qualif values(16507, 3031);
insert into qualif values(16507, 4041);
insert into qualif values(16302, 324);
insert into qualif values(16302, 4041);
insert into qualif values(15504, 324);
insert into qualif values(15504, 532);
insert into qualif values(15504, 3031);
insert into qualif values(15504, 4041);
insert into qualif values(27006, 4041);
insert into qualif values(15809, 324);



insert into varprix values(1, 1.5);
insert into varprix values(3, 1.25);
insert into varprix values(4, 0.75);
insert into varprix values(5, 0.3);
insert into varprix values(2, 1);



insert into vols values('AF2135', 'Paris', 'Londres', 800, 12.40, 13.45, 2000);
insert into vols values('BA0145', 'Londres', 'Madrid', 2000, 10, 12.30, 3500);
insert into vols values('BA5134', 'Londres', 'Moscou', 2500, 14.15, 17.30, 6000);
insert into vols values('BA6789', 'Moscou', 'Vienne', 1000, 19, 20.15, 4000);
insert into vols values('IT3487', 'Rome', 'Paris', 1000, 9.30, 11, 3000);
insert into vols values('IT7843', 'Paris', 'Rome', 1100, 19.30, 20.45, 3000);
insert into vols values('IB1020', 'Madrid', 'Rome', 1500, 15, 16.30, 2500);
insert into vols values('AF3140', 'Paris', 'Madrid', 1500, 13, 14.30, 2500);
insert into vols values('IB3150', 'Paris', 'Madrid', 1600, 13, 14.35, 2500);
insert into vols values('AF6071', 'Paris', 'New-York', 8000, 11, 17.15, 7000);
insert into vols values('BA2525', 'Paris','Londres', 800, 10, 11, 1800);
insert into vols values('AF2130', 'Paris', 'Londres', 800, 15.30, 16.35, 2200);
insert into vols values('BA4015', 'Londres', 'New-York', 8000, 14, 20.10, 4000);
insert into vols values('CA0012', 'Paris', 'Bruxelles', 500, 8, 9.35, 1000);
insert into vols values('CA0301', 'Bruxelles', 'Londres', 500, 10, 11.45, 1000);
