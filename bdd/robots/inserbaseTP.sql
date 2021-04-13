
insert into robots values ('Bob', 'TIM', 'USA', 1);
insert into robots values ('Gaston', 'SAAL', 'France', 1);
insert into robots values ('Zoe', 'AIRNI', 'France', 1);
insert into robots values ('Pok', 'SISCAN', 'Japan', 2) ;
insert into robots values ('Tom', 'GRRL', 'Germany', 2) ;
insert into robots values ('Toto', 'TIM', 'USA', 2);
insert into robots values ('Pikpik', 'SISCAN', 'Japan', 1);
insert into robots values ('Pyroli', 'GRRL', 'Germany', 2) ;

insert into competitions values ('WRC', 'Boston', 'USA', '03-MAR-00');
insert into competitions values ('IRM', 'Paris', 'France', '20-JUN-00');
insert into competitions values ('RIR', 'Tokyo', 'Japan', '15-MAR-00');

insert into inscriptions values ('Bob', 'WRC', 200);
insert into inscriptions values ('Bob', 'IRM', 50);
insert into inscriptions values ('Gaston', 'WRC', 100);
insert into inscriptions values ('Gaston', 'RIR', 600);
insert into inscriptions values ('Tom', 'WRC', 1000);
insert into inscriptions values ('Tom', 'IRM', 1000);
insert into inscriptions values ('Tom', 'RIR', 2000);
insert into inscriptions values ('Zoe', 'IRM', 1000);
insert into inscriptions values ('Pok', 'WRC', 500);
insert into inscriptions values ('Pok', 'RIR', 1500);
insert into inscriptions values ('Gaston', 'IRM', 200);
insert into inscriptions values ('Pikpik', 'WRC', NULL);
insert into inscriptions values ('Pyroli', 'WRC', NULL);
insert into inscriptions values ('Zoe', 'WRC', 250);
insert into inscriptions values ('Toto', 'WRC', 1500);


insert into epreuves values (31, 'WRC', 1, 'Le tennis', 'AM');
insert into epreuves values (32, 'WRC', 2, 'Le jardin', 'AM');
insert into epreuves values (41, 'WRC', 1, 'Le combat1', 'PM');
insert into epreuves values (42, 'WRC', 2, 'Le combat2', 'PM');
insert into epreuves values (31, 'RIR', 1, 'Toutterrain', 'AM');
insert into epreuves values (32, 'RIR', 2, 'Planif', 'PM');
insert into epreuves values (41, 'RIR', 1, 'Construct', 'AM');
insert into epreuves values (42, 'RIR', 2, 'Pugilat', 'PM');
insert into epreuves values (51, 'IRM', 1, 'Course1', 'AM');
insert into epreuves values (52, 'IRM', 2, 'Course2', 'PM');
insert into epreuves values (41, 'IRM', 1, 'Course1b', 'AM');


insert into resultats values ('Pok', 32, 'WRC', 30, 900);
insert into resultats values ('Pok', 42, 'WRC', 20, 150);
insert into resultats values ('Pok', 42, 'RIR', 40, 100);
insert into resultats values ('Gaston', 41, 'RIR', 40, 2000);
insert into resultats values ('Gaston', 41, 'WRC', 50, 800);
insert into resultats values ('Bob', 31, 'WRC', 60, 100); 
insert into resultats values ('Bob', 41, 'IRM', 100, 500);
insert into resultats values ('Tom', 42, 'WRC', 150, 900);
insert into resultats values ('Tom', 32, 'RIR', 90, 500);
insert into resultats values ('Toto', 42, 'WRC', 100, 500);
insert into resultats values ('Toto', 32, 'WRC', 400, 700);
insert into resultats values ('Zoe', 31, 'WRC', 90, 500);

 
-- (c) E. Desmontils, Falculté des sciences et des techniques de Nantes, Université de Nantes, Mars 2003
-- #e -mail : Emmanuel.Desmontils@info.univ-nantes.fr
