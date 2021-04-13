create table Lutin (idLutin int PRIMARY KEY, prenom varchar(25), pays varchar(20));

create table Enfant (idEnfant int PRIMARY KEY, prenom varchar(20), ville varchar(20), pays varchar(20));

create table Jouet (idJouet int PRIMARY KEY, nom varchar(25), prix int);

create table Commande (idCommande int PRIMARY KEY, idEnfant int, idJ int, 
constraint EnfantCO foreign key(idEnfant) references Enfant(idEnfant),
constraint JouetCO foreign key(idJ) references Jouet(idJouet));

create table Livraison (idLutin int, idCommande int PRIMARY KEY,
constraint CommandeLI foreign key(idCommande) references Commande(idCommande),
constraint LutinLI foreign key(idLutin)references Lutin(idLutin));
 

insert into Lutin values (1 , "Roudoudou" ,"France");
insert into Lutin values (2 , "Cachou" ,"France");
insert into Lutin values (3 , "Trotinou" ,"Laponie");
insert into Lutin values (4 , "Zebulon" ,"Laponie");
insert into Lutin values (5 , "Zelie" ,"Angleterre");
insert into Lutin values (6 , "Binky" ,"Angleterre");
insert into Lutin values (7, "Frisou" ,"Russie");
insert into Lutin values (8 , "Patachoux" ,"Russie");
insert into Lutin values (9 , "Filou" ,"Italie");
insert into Lutin values (10 , "Cookie" ,"Italie");

insert into Jouet values (1 , "PS4", 300);
insert into Jouet values (2 , "Barbie", 25);
insert into Jouet values (3 , "Lego Star Wars", 99);
insert into Jouet values (4 , "Docteur Maboul", 19);
insert into Jouet values (5 , "Playmobil Fusée", 62);
insert into Jouet values (6 , "Playmobil Ecole", 85);
insert into Jouet values (7 , "La bonne paie", 20);
insert into Jouet values (8 , "Cluedo Harry Potter",32);
insert into Jouet values (9 , "Mastermind", 18);
insert into Jouet values (10 , "Poupée", 33);
insert into Jouet values (11 , "Veilleuse", 16);
insert into Jouet values (12 , "Cuisine", 75);
insert into Jouet values (13 , "Etabli modulable", 39);

insert into Enfant values (1 , "Simon", "Nantes", "France");
insert into Enfant values (2 , "Samuel", "Nantes", "France");
insert into Enfant values (3 , "Blandine", "Nantes", "France");
insert into Enfant values (4 , "Alvin", "Rennes", "France");
insert into Enfant values (5 , "Christian", "Rennes", "France");
insert into Enfant values (6 , "Killian", "Rennes", "France");
insert into Enfant values (7 , "Emma", "Rome", "Italie");
insert into Enfant values (8 , "Oriane", "Florence", "Italie");
insert into Enfant values (9 , "Thierno", "Milan", "Italie");
insert into Enfant values (10 , "Malick", "Londres", "Angleterre");
insert into Enfant values (11 , "Hassane", "Londres", "Angleterre");
insert into Enfant values (12 , "Ludwig", "Londres", "Angleterre");
insert into Enfant values (13 , "Matthieu", "Moscou", "Russie");
insert into Enfant values (14 , "Simon", "Moscou", "Russie");
insert into Enfant values (15 , "Clémentine", "Rome", "Italie");
insert into Enfant values (16 , "Mickael", "Moscou", "Russie");
insert into Enfant values (17 , "Luca", "Paris", "France");
insert into Enfant values (18 , "Ewen", "Moscou", "Russie");

insert into Commande values (1,1,1);
insert into Commande values (2,2,9);
insert into Commande values (3,3,10);
insert into Commande values (4,3,8);
insert into Commande values (5,4,1);
insert into Commande values (6,4,7);
insert into Commande values (7,4,13);
insert into Commande values (8,5,3);
insert into Commande values (9,5,13); 
insert into Commande values (10,6,1);
insert into Commande values (11,7,12); 
insert into Commande values (12,8,2);
insert into Commande values (13,8,6);
insert into Commande values (14,9,4);
insert into Commande values (15,16,4);
insert into Commande values (17,18,1);
insert into Commande values (18,12,5);
insert into Commande values (19,1,10);
insert into Commande values (20,18,7);

insert into Livraison values (1,1);
insert into Livraison values (1,2);
insert into Livraison values (2,3);
insert into Livraison values (6,4);
insert into Livraison values (7,5);
insert into Livraison values (7,6);
insert into Livraison values (4,7);
insert into Livraison values (5,8);
insert into Livraison values (10,9); 
insert into Livraison values (9,10);
insert into Livraison values (5,11); 
insert into Livraison values (6,12);
insert into Livraison values (8,13);
insert into Livraison values (7,17); 
insert into Livraison values (7,20);


