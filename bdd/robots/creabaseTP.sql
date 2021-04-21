
create table robots
(nom varchar(6),
labo varchar(6),
pays varchar(8),
cat int,
primary key (nom)) ;


create table inscriptions
(nom varchar(6),
compet varchar(4),
montant real,
primary key (nom, compet));


create table competitions
(compet varchar(4) primary key,
ville varchar(10),
pays varchar(8),
datc date);

create table epreuves
(numep int,
compet varchar(4),
cat int,
titre varchar(12),
demij char(2),
primary key (numep, compet) );

create table resultats
(nom varchar(6),
numep int,
compet varchar(4),
nbpts int,
gain real,
primary key(nom, numep, compet) );

-- (c) E. Desmontils, Falculté des sciences et des techniques de Nantes, Université de Nantes, Mars 2003
-- #e -mail : Emmanuel.Desmontils@info.univ-nantes.fr
