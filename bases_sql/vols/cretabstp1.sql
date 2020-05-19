create table avions 
(noav number(4),
type VARCHAR2(10),
distmax number,
primary key(noav) );


create table clients 
(nocl number(5),
nomcl varchar2(10),
prenomcl varchar2(10),
nomsoc varchar2(10),
primary key(nocl) );


create table employes (
noe number(5),
nome VARCHAR2(10),
naiss date,
embauche date,
derpromo date,
salaire  number,
indem number,
primary key(noe) );


create table vols (
nov char(6),
origin VARCHAR2(10),
destin VARCHAR2(10),
distance number,
hdep number(4,2),
harr number(4,2),
prixbase number,
primary key(nov)  );



create table qualif (
noe number(5),
noav number(4),
primary key(noe, noav),
foreign key(noe) references employes(noe),
foreign key(noav) references avions(noav)  );


create table reservs (
nocl number(5),
nov char(6),
jour date not null,
class number(1),
primary key(nocl, nov),
foreign key(nocl) references clients(nocl),
foreign key(nov) references vols(nov) );


create table varprix (
class number(1),
coeff number(4,2),
primary key(class) );


