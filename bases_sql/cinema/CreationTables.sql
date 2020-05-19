create table Acteur
(id_act int not null,
 nom varchar(20) not null,
 prenom varchar(20) not null,
 age int,
 primary key (id_act)) ;

create table Film
(id int not null, 
 titre varchar(30) not null,
 genre varchar(10) not null,
 annee int,
 primary key (id)) ;

create table Cinema
(id_cine int not null,
 nom varchar(20) not null,
 ville varchar(20) not null,
 tarifR int,
 tarifP int,
 primary key (id_cine)) ;

create table Projection
(id_cine int not null,
 id_film int not null, 
 dateP date,
 nbentrees int,
 primary key (id_cine,id_film,dateP),
 CONSTRAINT P_cinema FOREIGN KEY (id_cine) REFERENCES Cinema (id_cine),
 CONSTRAINT P_film FOREIGN KEY (id_film) REFERENCES Film (id));

 
create table Jouer
(id_act int not null,
 id_film int not null, 
 role varchar(25) not null,
 salaire int not null,
 primary key (id_act, id_film),
 CONSTRAINT J_acteur FOREIGN KEY (id_act) REFERENCES Acteur (id_act),
 CONSTRAINT J_film FOREIGN KEY (id_film) REFERENCES Film (id));

