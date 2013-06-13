<?php

class DemoBuilder {
    
    protected $id = null;
    
    //http://www.world-english.org/boys_names_list.htm
    protected $name_f = array(
            'Aaron', 'Abbott', 'Abel', 'Abner', 'Abraham', 'Adam', 'Addison', 'Adler',
            'Adley', 'Adrian', 'Aedan', 'Aiken', 'Alan', 'Alastair', 'Albern', 'Albert',
            'Albion', 'Alden', 'Aldis', 'Aldrich', 'Alexander', 'Alfie', 'Alfred',
            'Algernon', 'Alston', 'Alton', 'Alvin', 'Ambrose', 'Amery', 'Amos',
            'Andrew', 'Angus', 'Ansel', 'Anthony', 'Archer', 'Archibald', 'Arlen',
            'Arnold', 'Arthur', 'Arvel', 'Atwater', 'Atwood', 'Aubrey', 'Austin',
            'Avery', 'Axel', 'Baird', 'Baldwin', 'Barclay', 'Barnaby', 'Baron',
            'Barrett', 'Barry', 'Bartholomew', 'Basil', 'Benedict', 'Benjamin',
            'Benton', 'Bernard', 'Bert', 'Bevis', 'Blaine', 'Blair', 'Blake', 'Bond',
            'Boris', 'Bowen', 'Braden', 'Bradley', 'Brandan', 'Brent', 'Bret', 'Brian',
            'Brice', 'Brigham', 'Brock', 'Broderick', 'Brooke', 'Bruce', 'Bruno',
            'Bryant', 'Buck', 'Bud', 'Burgess', 'Burton', 'Byron', 'Cadman', 'Calvert',
            'Caldwell', 'Caleb', 'Calvin', 'Carrick', 'Carl', 'Carlton', 'Carney',
            'Carroll', 'Carter', 'Carver', 'Cary', 'Casey', 'Casper', 'Cecil', 'Cedric',
            'Chad', 'Chalmers', 'Chandler', 'Channing', 'Chapman', 'Charles', 'Chatwin',
            'Chester', 'Christian', 'Christopher', 'Clarence', 'Claude', 'Clayton',
            'Clifford', 'Clive', 'Clyde', 'Coleman', 'Colin', 'Collier', 'Conan',
            'Connell', 'Connor', 'Conrad', 'Conroy', 'Conway', 'Corwin', 'Crispin',
            'Crosby', 'Culbert', 'Culver', 'Curt', 'Curtis', 'Cuthbert', 'Craig',
            'Cyril', 'Dale', 'Dalton', 'Damon', 'Daniel', 'Darcy', 'Darian', 'Darell',
            'David', 'Davin', 'Dean', 'Declan', 'Delmar', 'Denley', 'Dennis', 'Derek',
            'Dermot', 'Derwin', 'Des', 'Dexter', 'Dillon', 'Dion', 'Dirk', 'Dixon',
            'Dominic', 'Donald', 'Dorian', 'Douglas', 'Doyle', 'Drake', 'Drew',
            'Driscoll', 'Dudley', 'Duncan', 'Durwin', 'Dwayne', 'Dwight', 'Dylan',
            'Earl', 'Eaton', 'Ebenezer', 'Edan', 'Edgar', 'Edric', 'Edmond', 'Edward',
            'Edwin', 'Efrain', 'Egan', 'Egbert', 'Egerton', 'Egil', 'Elbert', 'Eldon',
            'Eldwin', 'Eli', 'Elias', 'Eliot', 'Ellery', 'Elmer', 'Elroy', 'Elton',
            'Elvis', 'Emerson', 'Emmanuel', 'Emmett', 'Emrick', 'Enoch', 'Eric',
            'Ernest', 'Errol', 'Erskine', 'Erwin', 'Esmond', 'Ethan', 'Ethen', 'Eugene',
            'Evan', 'Everett', 'Ezra', 'Fabian', 'Fairfax', 'Falkner', 'Farley',
            'Farrell', 'Felix', 'Fenton', 'Ferdinand', 'Fergal', 'Fergus', 'Ferris',
            'Finbar', 'Fitzgerald', 'Fleming', 'Fletcher', 'Floyd', 'Forbes', 'Forrest',
            'Foster', 'Fox', 'Francis', 'Frank', 'Frasier', 'Frederick', 'Freeman',
            'Gabriel', 'Gale', 'Galvin', 'Gardner', 'Garret', 'Garrick', 'Garth',
            'Gavin', 'George', 'Gerald', 'Gideon', 'Gifford', 'Gilbert', 'Giles',
            'Gilroy', 'Glenn', 'Goddard', 'Godfrey', 'Godwin', 'Graham', 'Grant',
            'Grayson', 'Gregory', 'Gresham', 'Griswald', 'Grover', 'Guy', 'Hadden',
            'Hadley', 'Hadwin', 'Hal', 'Halbert', 'Halden', 'Hale', 'Hall', 'Halsey',
            'Hamlin', 'Hanley', 'Hardy', 'Harlan', 'Harley', 'Harold', 'Harris',
            'Hartley', 'Heath', 'Hector', 'Henry', 'Herbert', 'Herman', 'Homer',
            'Horace', 'Howard', 'Hubert', 'Hugh', 'Humphrey', 'Hunter', 'Ian', 'Igor',
            'Irvin', 'Isaac', 'Isaiah', 'Ivan', 'Iver', 'Ives', 'Jack', 'Jacob',
            'James', 'Jarvis', 'Jason', 'Jasper', 'Jed', 'Jeffrey', 'Jeremiah',
            'Jerome', 'Jesse', 'John', 'Jonathan', 'Joseph', 'Joshua', 'Justin', 'Kane',
            'Keene', 'Keegan', 'Keaton', 'Keith', 'Kelsey', 'Kelvin', 'Kendall',
            'Kendrick', 'Kenneth', 'Kent', 'Kenway', 'Kenyon', 'Kerry', 'Kerwin',
            'Kevin', 'Kiefer', 'Kilby', 'Kilian', 'Kim', 'Kimball', 'Kingsley', 'Kirby',
            'Kirk', 'Kit', 'Kody', 'Konrad', 'Kurt', 'Kyle', 'Lambert', 'Lamont',
            'Lancelot', 'Landon', 'Landry', 'Lane', 'Lars', 'Laurence', 'Lee', 'Leith',
            'Leonard', 'Leroy', 'Leslie', 'Lester', 'Lincoln', 'Lionel', 'Lloyd',
            'Logan', 'Lombard', 'Louis', 'Lowell', 'Lucas', 'Luther', 'Lyndon',
            'Maddox', 'Magnus', 'Malcolm', 'Melvin', 'Marcus', 'Mark', 'Marlon',
            'Martin', 'Marvin', 'Matthew', 'Maurice', 'Max', 'Medwin', 'Melville',
            'Merlin', 'Michael', 'Milburn', 'Miles', 'Monroe', 'Montague', 'Montgomery',
            'Morgan', 'Morris', 'Morton', 'Murray', 'Nathaniel', 'Neal', 'Neville',
            'Nicholas', 'Nigel', 'Noel', 'Norman', 'Norris', 'Olaf', 'Olin', 'Oliver',
            'Orson', 'Oscar', 'Oswald', 'Otis', 'Owen', 'Paul', 'Paxton', 'Percival',
            'Perry', 'Peter', 'Peyton', 'Philbert', 'Philip', 'Phineas', 'Pierce',
            'Quade', 'Quenby', 'Quillan', 'Quimby', 'Quentin', 'Quinby', 'Quincy',
            'Quinlan', 'Quinn', 'Ralph', 'Ramsey', 'Randolph', 'Raymond', 'Reginald',
            'Renfred', 'Rex', 'Rhett', 'Richard', 'Ridley', 'Riley', 'Robert',
            'Roderick', 'Rodney', 'Roger', 'Roland', 'Rolf', 'Ronald', 'Rory',
            'Ross', 'Roswell', 'Roy', 'Royce', 'Rufus', 'Rupert', 'Russell',
            'Ryan'
    );

    protected $name_l = array (
            'Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Miller', 'Davis',
            'Garcia', 'Rodriguez', 'Wilson', 'Martinez', 'Anderson', 'Taylor',
            'Thomas', 'Hernandez', 'Moore', 'Martin', 'Jackson', 'Thompson', 'White',
            'Lopez', 'Lee', 'Gonzalez', 'Harris', 'Clark', 'Lewis', 'Robinson',
            'Walker', 'Perez', 'Hall', 'Young', 'Allen', 'Sanchez', 'Wright', 'King',
            'Scott', 'Green', 'Baker', 'Adams', 'Nelson', 'Hill', 'Ramirez', 'Campbell',
            'Mitchell', 'Roberts', 'Carter', 'Phillips', 'Evans', 'Turner', 'Torres',
            'Parker', 'Collins', 'Edwards', 'Stewart', 'Flores', 'Morris', 'Nguyen',
            'Murphy', 'Rivera', 'Cook', 'Rogers', 'Morgan', 'Peterson', 'Cooper',
            'Reed', 'Bailey', 'Bell', 'Gomez', 'Kelly', 'Howard', 'Ward', 'Cox', 'Diaz',
            'Richardson', 'Wood', 'Watson', 'Brooks', 'Bennett', 'Gray', 'James',
            'Reyes', 'Cruz', 'Hughes', 'Price', 'Myers', 'Long', 'Foster', 'Sanders',
            'Ross', 'Morales', 'Powell', 'Sullivan', 'Russell', 'Ortiz', 'Jenkins',
            'Gutirrez', 'Perry', 'Butler', 'Barnes', 'Fisher'
    );

    protected $countries = array ('US');


//http://en.wikipedia.org/wiki/List_of_cities,_towns,_and_villages_in_the_United_States
    protected $cities = array(
            'NY' => array (
                            'Albany', 'Amsterdam', 'Auburn', 'Batavia', 'Beacon', 'Binghamton',
                            'Buffalo', 'Canandaigua', 'Cohoes', 'Corning', 'Cortland', 'Dunkirk',
                            'Elmira', 'Fulton', 'Geneva', 'Glen Cove', 'Glens Falls', 'Gloversville',
                            'Hornell', 'Hudson', 'Ithaca', 'Jamestown', 'Johnstown', 'Kingston',
                            'Lackawanna', 'Little Falls', 'Lockport', 'Long Beach', 'Mechanicville',
                            'Middletown', 'Mount Vernon', 'New Rochelle', 'New York City', 'Newburgh',
                            'Niagara Falls', 'North Tonawanda', 'Norwich', 'Ogdensburg', 'Olean',
                            'Oneida', 'Oneonta', 'Oswego', 'Peekskill', 'Plattsburgh', 'Port Jervis',
                            'Poughkeepsie', 'Rensselaer', 'Rochester', 'Rome', 'Rye', 'Salamanca',
                            'Saratoga Springs', 'Schenectady', 'Sherrill', 'Syracuse', 'Tonawanda',
                            'Troy', 'Utica', 'Watertown', 'Watervliet', 'White Plains', 'Yonkers',
            ),
            'AL' =>  array (
                            'Abbeville', 'Adamsville', 'Addison', 'Akron', 'Alabaster', 'Albertville',
                            'Alexander City', 'Aliceville', 'Allgood', 'Altoona', 'Andalusia',
                            'Anderson', 'Anniston', 'Arab', 'Ardmore', 'Argo', 'Ariton', 'Arley',
                            'Ashford', 'Ashland', 'Ashville', 'Athens', 'Atmore', 'Attalla', 'Auburn',
                            'Autaugaville', 'Avon', 'Babbie', 'Baileyton', 'Banks', 'Bay Minette',
                            'Bayou La Batre', 'Bear Creek', 'Beatrice', 'Beaverton', 'Belk', 'Benton',
                            'Berry', 'Bessemer', 'Billingsley', 'Birmingham', 'Black', 'Blountsville',
                            'Blue Mountain', 'Blue Springs', 'Boaz', 'Boligee', 'Bon Air',
                            'Branchville', 'Brantley', 'Brent', 'Brewton', 'Bridgeport',
                            'Brighton', 'Brilliant', 'Brookside', 'Brookwood', 'Brundidge', 'Butler',
                            'Calera', 'Camden', 'Camp Hill', 'Carbon Hill', 'Cardiff', 'Carolina',
                            'Carrollton', 'Castleberry', 'Cedar Bluff', 'Centre', 'Centreville',
                            'Chatom', 'Chelsea', 'Cherokee', 'Chickasaw', 'Childersburg', 'Citronelle',
                            'Clanton', 'Clayhatchee', 'Clayton', 'Cleveland', 'Clio', 'Coaling',
                            'Coffee Springs', 'Coffeeville', 'Coker', 'Collinsville', 'Colony',
                            'Columbia', 'Columbiana', 'Coosada', 'Cordova', 'Cottonwood', 'County Line',
                            'Courtland', 'Cowarts', 'Creola', 'Crossville', 'Cuba', 'Cullman',
                            'Dadeville', 'Daleville', 'Daphne', 'Dauphin Island', 'Daviston',
                            'Dayton', 'Deatsville', 'Decatur', 'Demopolis', 'Detroit', 'Dodge City',
                            'Dora', 'Dothan', 'Double Springs', 'Douglas', 'Dozier', 'Dutton',
                            'East Brewton', 'Eclectic', 'Edwardsville', 'Elba', 'Elberta', 'Eldridge',
                            'Elkmont', 'Elmore', 'Emelle', 'Enterprise', 'Epes', 'Ethelsville',
                            'Eufaula', 'Eunola', 'Eutaw', 'Eva', 'Evergreen', 'Excel', 'Fairfield',
                            'Fairhope', 'Fairview', 'Falkville', 'Faunsdale', 'Fayette', 'Five Points',
                            'Flomaton', 'Florala', 'Florence', 'Foley', 'Forkland', 'Fort Deposit',
                            'Fort Payne', 'Franklin', 'Frisco City', 'Fruithurst', 'Fulton',
                            'Fultondale', 'Fyffe', 'Gadsden', 'Gainesville', 'Gantt', 'Gantts Quarry',
                            'Garden City', 'Gardendale', 'Gaylesville', 'Geiger', 'Geneva', 'Georgiana',
                            'Geraldine', 'Gilbertown', 'Glen Allen', 'Glencoe', 'Glenwood', 'Goldville',
                            'Good Hope', 'Goodwater', 'Gordo', 'Gordon', 'Gordonville', 'Goshen',
                            'Grant', 'Graysville', 'Greensboro', 'Greenville', 'Grimes', 'Grove Hill',
                            'Guin', 'Gulf Shores', 'Guntersville', 'Gurley', 'Gu-Win', 'Hackleburg',
                            'Haleburg', 'Haleyville', 'Hamilton', 'Hammondville', 'Hanceville',
                            'Harpersville', 'Hartford', 'Hartselle', 'Hayden', 'Hayneville',
                            'Headland', 'Heath', 'Heflin', 'Helena', 'Henagar', 'Highland Lake',
                            'Hillsboro', 'Hobson City', 'Hodges', 'Hokes Bluff', 'Holly Pond',
                            'Hollywood', 'Homewood', 'Hoover', 'Horn Hill', 'Hueytown', 'Huntsville',
                            'Hurtsboro', 'Hytop', 'Ider', 'Indian Springs Village', 'Irondale',
                            'Jackson', 'Jacksons\' Gap', 'Jacksonville', 'Jasper', 'Jemison', 'Kansas',
                            'Kennedy', 'Killen', 'Kimberly', 'Kinsey', 'Kinston', 'La Fayette',
                            'Lake View', 'Lakeview', 'Lanett', 'Langston', 'Leeds', 'Leesburg',
                            'Leighton', 'Lester', 'Level Plains', 'Lexington', 'Libertyville',
                            'Lincoln', 'Linden', 'Lineville', 'Lipscomb', 'Lisman', 'Littleville',
                            'Livingston', 'Loachapoka', 'Lockhart', 'Locust Fork', 'Louisville',
                            'Lowndesboro', 'Loxley', 'Luverne', 'Lynn', 'Macedonia', 'Madison',
                            'Madrid', 'Malvern', 'Maplesville', 'Margaret', 'Marion', 'Maytown',
                            'McIntosh', 'McKenzie', 'McMullen', 'Memphis', 'Mentone', 'Midfield',
                            'Midland City', 'Midway', 'Millbrook', 'Millport', 'Millry', 'Mobile',
                            'Monroeville', 'Montevallo', 'Montgomery', 'Moody', 'Mooresville', 'Morris',
                            'Mosses', 'Moulton', 'Moundville', 'Mount Vernon', 'Mountain Brook',
                            'Mountainboro', 'Mulga', 'Muscle Shoals', 'Myrtlewood', 'Napier Field',
                            'Natural Bridge', 'Nauvoo', 'Nectar', 'Needham', 'New Brockton', 'New Hope',
                            'New Site', 'Newbern', 'Newton', 'Newville', 'North Bibb', 'North Courtland',
                            'North Johns', 'Northport', 'Notasulga', 'Oak Grove', 'Oak Hill', 'Oakman',
                            'Odenville', 'Ohatchee', 'Oneonta', 'Onycha', 'Opelika', 'Opp',
                            'Orange Beach', 'Orrville', 'Owens Cross Roads', 'Oxford', 'Ozark',
                            'Paint Rock', 'Parrish', 'Pelham', 'Pell City', 'Pennington', 'Petrey',
                            'Phenix City', 'Phil Campbell', 'Pickensville', 'Piedmont', 'Pike Road',
                            'Pinckard', 'Pine Apple', 'Pine Hill', 'Pine Ridge', 'Pisgah',
                            'Pleasant Grove', 'Pleasant Groves', 'Pollard', 'Powell', 'Prattville',
                            'Priceville', 'Prichard', 'Providence', 'Ragland', 'Rainbow City',
                            'Rainsville', 'Ranburne', 'Red Bay', 'Red Level', 'Reece City', 'Reform',
                            'Rehobeth', 'Repton', 'Ridgeville', 'River Falls', 'Riverside', 'Riverview',
                            'Roanoke', 'Robertsdale', 'Rockford', 'Rogersville', 'Rosa', 'Russellville',
                            'Rutledge', 'Samson', 'Sand Rock', 'Sanford', 'Saraland', 'Sardis City',
                            'Satsuma', 'Scottsboro', 'Section', 'Selma', 'Sheffield', 'Shiloh',
                            'Shorter', 'Silas', 'Silverhill', 'Sipsey', 'Skyline', 'Slocomb', 'Snead',
                            'Somerville', 'South Vinemont', 'Southside', 'Spanish Fort',
                            'Springville', 'St. Florian', 'Steele', 'Stevenson', 'Sulligent', 'Sumiton',
                            'Summerdale', 'Susan Moore', 'Sweet Water', 'Sylacauga', 'Sylvan Springs',
                            'Sylvania', 'Talladega Springs', 'Talladega', 'Tallassee', 'Tarrant',
                            'Taylor', 'Thomaston', 'Thomasville', 'Thorsby', 'Town Creek', 'Toxey',
                            'Trafford', 'Triana', 'Trinity', 'Troy', 'Trussville', 'Tuscaloosa',
                            'Tuscumbia', 'Tuskegee', 'Union Grove', 'Union Springs', 'Union',
                            'Uniontown', 'Valley Head', 'Valley', 'Vance', 'Vernon', 'Vestavia Hills',
                            'Vina', 'Vincent', 'Vredenburgh', 'Wadley', 'Waldo', 'Walnut Grove',
                            'Warrior', 'Waterloo', 'Waverly', 'Weaver', 'Webb', 'Wedowee',
                            'West Blocton', 'West Jefferson', 'West Point', 'Wetumpka', 'White Hall',
                            'Wilsonville', 'Wilton', 'Winfield', 'Woodland', 'Woodville',
                            'Yellow Bluff', 'York'
            ),
            'AZ' => array (
                            'Apache Junction', 'Avondale', 'Benson', 'Bisbee', 'Buckeye',
                            'Bullhead City', 'Camp Verde', 'Carefree', 'Casa Grande', 'Cave Creek',
                            'Chandler', 'Chino Valley', 'Clarkdale', 'Clifton', 'Colorado City',
                            'Coolidge', 'Cottonwood', 'Dewey-Humboldt', 'Douglas', 'Duncan', 'Eagar',
                            'El Mirage', 'Eloy', 'Flagstaff', 'Florence', 'Fountain Hills', 'Fredonia',
                            'Gila Bend', 'Gilbert', 'Glendale', 'Globe', 'Goodyear', 'Guadalupe',
                            'Hayden', 'Holbrook', 'Huachuca City', 'Jerome', 'Kearny', 'Kingman',
                            'Lake Havasu City', 'Litchfield Park', 'Mammoth', 'Marana', 'Maricopa',
                            'Mesa', 'Miami', 'Nogales', 'Oro Valley', 'Page', 'Paradise Valley',
                            'Parker', 'Patagonia', 'Payson', 'Peoria', 'Phoenix', 'Pima',
                            'Pinetop-Lakeside', 'Prescott', 'Prescott Valley', 'Quartzsite',
                            'Queen Creek', 'Safford', 'Sahuarita', 'San Luis', 'Scottsdale', 'Sedona',
                            'Show Low', 'Sierra Vista', 'Snowflake', 'Somerton', 'South Tucson',
                            'Springerville', 'St. Johns', 'Star Valley', 'Superior', 'Surprise',
                            'Taylor', 'Tempe', 'Thatcher', 'Tolleson', 'Tombstone', 'Tucson', 'Wellton',
                            'Wickenburg', 'Willcox', 'Williams', 'Winkelman', 'Winslow', 'Youngtown',
                            'Yuma'
            )
    );

    protected $states = array (
            'US'=> array('NY', 'AL', 'AZ')
    );

    //Most popular names of streets
    protected $streets = array (
            'Second', 'Third', 'First', 'Fourth', 'Park', 'Fifth', 'Main', 'Sixth',
            'Oak', 'Seventh', 'Pine', 'Maple', 'Cedar', 'Eighth', 'Elm', 'View',
            'Washington', 'Ninth', 'Lake', 'Hil'
    );

    protected $productTitles = array(
            'Gold Membership', 'Silver Membership', 'Bronze Membership',
            'Platinum Membership', 'Special Programm', 'Latest Sport News',
            'Wheight loss programm', 'Education Course 1', 'Education Course 2',
            'Education Course 3', 'Education Course 4', 'Training Programm',
            'Special Training Programm', 'VIP Membership', 'Newsletters'
    );

    public function __construct(Am_Di $di, $id) {
        $this->di = $di;
        $this->id = $id;
    }
    
    public function getDi()
    {
        return $this->di;
    }
    
    public function getID() {
        return $this->id;
    }

    /*
     * @return User
    */
    public function createUser($emailDomain = 'cgi-central.int') {
        $user = $this->getDi()->userTable->createRecord();

        $user->name_f = $this->getRandomFromArray('name_f');
        $user->name_l = $this->getRandomFromArray('name_l');
        $this->setPass($user);
        $user->login  = $this->generateLogin($user->name_f, $user->name_l);
        $user->email  = $this->generateEmail($user->login, $emailDomain);

        $address = $this->createAddress();

        $user->country = $address->country;
        $user->state   = $address->state;
        $user->city    = $address->city;
        $user->street  = $address->street;
        $user->zip     = $address->zip;

        $user->save();
        
        $user->data()->set('demo-id', $this->getID());
        $user->save();

        return $user;
    }
    
    /**
     *
     * @param User 
     * @param Am_Paysystem_Abstract $payplugin
     * @param array $product_ids array of product_id to use for generation
     * @param int $invCnt count of invoices per user
     * @param int $invVar variation of count of invoices per user
     * @param int $prdCnt count of products per invoice
     * @param int $prdVar variation of products per invoice 
     */
    public function createInvoices($user, $payplugin, $product_ids, $invCnt, $invVar, $prdCnt, $prdVar) {
        $invoiceLimit = $this->getLimit($invCnt, $invVar);

        for($j=1; $j<=$invoiceLimit; $j++) {
            //subscribe user for some subscriptions
            $invoice = $this->getDi()->invoiceTable->createRecord();

            $productLimit = max(1, $this->getLimit($prdCnt,$prdVar));

            for ($k=1; $k<=$productLimit; $k++) {
                $invoice->add(Am_Di::getInstance()->productTable->load(array_rand($product_ids, 1)), 1);
            }
            $invoice->setUser($user);
            $invoice->setPaysystem($payplugin->getId());
            $invoice->calculate();
            $invoice->save();
            
            $transaction = new Am_Paysystem_Transaction_Manual($payplugin);
            $transaction->setAmount($invoice->first_total)
                        ->setInvoice($invoice)
                        ->setTime(new DateTime('-'.rand(0,200).' days'))
                        ->setReceiptId('demo-'.uniqid() . microtime(true));
            $invoice->addPayment($transaction); 
//            $cc = $this->createCcRecord($user);
//
//            Am_Paysystem_Transaction_CcDemo::_setTime(new DateTime('-'.rand(0,200).' days'));
//            $payplugin->doBill($invoice, true, $cc);
            
            $transaction = null;
            unset($transaction);

            $invoice = null;
            unset($invoice);
        }
    }
    
    /**
     *
     * @param type $cnt
     * @param type $pcCnt 
     * @return array array of generated product_id
     */
    public function createProducts($cnt, $pcCnt=0) {
        $product_ids = array();
        $pcIds = array();
        for ($i=0; $i<$pcCnt; $i++) {
            $pc = $this->getDi()->productCategoryTable->createRecord();

            $pc->title = 'Group of Access' . $i ;
            //$pc->code = $this->getID();
            $pc->save();

            $pcIds[] = $pc->pk();
        } 
        
        for ($i=1; $i<=$cnt; $i++) {
            $product = $this->createProduct();

            if ($pcCnt) {
                shuffle($pcIds);
                $product->setCategories(array($pcIds[0]));
            }
            $product_ids[$product->pk()] = $product->pk();

            $product = null;
            unset($product);
        }
        return $product_ids;
    }

    /*
     * @return Product
    */
    public function createProduct() {
        $product = $this->getDi()->productTable->createRecord();

        $product->title        = $this->getRandomFromArray('productTitles');
        $product->description  = 'Short description of this subscription';

        $product->save();

        $bp = $product->createBillingPlan();
        $bp->title = "default";
        $bp->first_price = rand(0, 200);
        $bp->first_period = rand(1,12) . 'm';
        $bp->rebill_times = 0;
        $bp->insert();
        
        $product->setBillingPlan($bp);
        $product->data()->set('demo-id', $this->getID());
        $product->save();
        return $product;
    }

    public function createAddress() {

        $address = new stdClass();

        $address->country = $this->getRandomFromArray('countries');
        $address->state   = $this->generateState($address->country);
        $address->city    = $this->generateCity($address->state);
        $address->street  = $this->generateStreet();
        $address->zip     = $this->generateZip();

        return $address;
    }
    
    protected function setPass(User $user){
        $user->pass = $this->getRandomPassHash();
    }

    protected function getRandomPassHash() {
        static $passHash = null;

        if (is_null($passHash)) {
            $ph = new PasswordHash(4, true);
            $passHash = array();
            for($i=0; $i<5; $i++) {
                $passHash[] = $ph->HashPassword($this->generatePass());
            }
        }

        return $passHash[rand(0, count($passHash)-1)];
    }
    
    protected function createCcRecord(User $user)
    {
        $cc = $this->getDi()->ccRecordTable->createRecord();
        $cc->user_id = $user->pk();
        $cc->cc_number = rand(0, 100) > 20 ? '4111111111111111' : '4111111111119999';
        $cc->cc_expire = date('my', time() + 3600 * 24 * 366);
        $cc->cc_name_f = $user->name_f;
        $cc->cc_name_l = $user->name_l;
        $cc->cc_country = $user->country;
        $cc->cc_street = $user->street;
        $cc->cc_city = $user->city;
        $cc->cc_state = $user->state;
        $cc->cc_zip = $user->zip;

        return $cc;
    }
    
    protected function getLimit($value, $variation) {
        $res = $value + rand(0, 2 * $variation) - $variation;
        return (int)$res;
    }

    protected function getRandomFromArray($arrayName) {
        $array = $this->$arrayName;
        settype($array, 'array');

        return $array[ array_rand($array) ];
    }

    protected function generateState($country) {
        $states = $this->states;
        settype($states, 'array');

        return $states[ $country ][ array_rand($states[ $country ]) ];
    }

    protected function generateCity($state) {
        $cities = $this->cities;
        settype($cities, 'array');

        return $cities[ $state ][ array_rand($cities[ $state ]) ];
    }

    protected function generateStreet() {
        $street = $this->getRandomFromArray('streets');

        return $street . ', ' . rand(1,200);
    }

    protected function generateZip() {
        return str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    protected function generateEmail($login, $emailDomain) {
        $email = $login . '@' . $emailDomain;
        return $email;
    }

    protected function generateLogin($name_f, $name_l) {
        $login = $name_f . $name_l . substr(md5(rand(1000000, 9999999) . microtime()), 0, 8);
        return strtolower($login);
    }

    protected function generatePass() {
        return rand(10000, 99999);
    }

}

class Am_Grid_Action_DemoDel extends Am_Grid_Action_Abstract {
    protected $title = "Delete";
    protected $id = "delete";
    public function __construct()
    {
        parent::__construct();
        $this->setTarget('_top');
    }
    public function getUrl($record = null, $id = null) {
        return Am_Controller::makeUrl('admin-build-demo', 'delete', null, array(
                'id'=>$record->id
        ));
    }
    public function run()
    {
        ;//
    }
}

class Am_Form_Admin_BuildDemoForm extends Am_Form_Admin {
    function init() {
        $this->addText('users_count')
                ->setLabel(___('Generate Users'))
                ->setValue(100);
        $this->addText('email_domain')
                ->setLabel(array(___('Email Domain'), ___('used to generate email address for users')))
                ->setValue('cgi-central.int');

        if ($this->isProductsExists()) {
            $this->addCheckbox('do_not_generate_products', array('checked'=>'checked'))
                    ->setLabel(
                    ___("Do not generate products\n".
                    "use existing products for demo records")
                    )
                    ->setId('form-do-not-generate-products');

            $script = <<<CUT
$(function() {

    function toggle_do_not_generate_products() {
        if ($('input[name=do_not_generate_products]').prop('checked')) {
            $('#form-products-count').parents('.row').hide();
        } else {
            $('#form-products-count').parents('.row').show();
        }
    }

    toggle_do_not_generate_products()
    
    $('input[name=do_not_generate_products]').bind('change', function(){
        toggle_do_not_generate_products();
    })
});
CUT;

            $this->addScript('script')->setScript(
                    $script
            );
        }

        $this->addText('products_count', array('size'=>3))
                ->setLabel(array(___('Generate Products')))
                ->setValue(3)
                ->setId('form-products-count');

        $gr = $this->addGroup()->setLabel(array(___('Invoices Per User')));
        $gr->addText('invoices_per_user', array('size'=>3))
                ->setValue(2);
        $gr->addStatic()->setContent('&nbsp;+/-');
        $gr->addText('invoices_per_user_variation', array('size'=>3))
                ->setValue(1);

        $gr = $this->addGroup()->setLabel(___('Products Per Invoice'));
        $gr->addText('products_per_invoice', array('size'=>3))->setValue(2);
        $gr->addStatic()->setContent('&nbsp;+/-');
        $gr->addText('products_per_invoice_variation', array('size'=>3))->setValue(1);

        parent::init();
        $this->addSaveButton(___('Generate'));
    }

    function isProductsExists() {
        return (boolean)Am_Di::getInstance()->productTable->count();
    }
}


class AdminBuildDemoController extends Am_Controller {
    public function checkAdminPermissions(Admin $admin)
    {
        return $admin->isSuper();
    }

    /** @var Zend_Session_Namespace */
    protected $session;


    public function init() {
        $this->session = new Zend_Session_Namespace('amember_build_demo');
        foreach ($this->getDi()->plugins_protect->loadEnabled()->getAllEnabled() as $pl)
            $pl->destroy();
    }

    function indexAction() 
    {
        $this->view->title = ___("Build Demo");
        $this->session->unsetAll();

        $form = new Am_Form_Admin_BuildDemoForm();

        if ($form->isSubmitted()) {
            $form->setDataSources(array(
                    $this->getRequest()
            ));
        }

        if ($form->isSubmitted() && $form->validate()) 
        {
            $values = $form->getValue();
            $this->session->params = array();
            $this->session->params['email_domain'] = $values['email_domain'];
            $this->session->params['users_count'] = $values['users_count'];
            $this->session->params['products_count'] = $values['products_count'];
            $this->session->params['invoices_per_user'] = $values['invoices_per_user'];
            $this->session->params['invoices_per_user_variation'] = $values['invoices_per_user_variation'];
            $this->session->params['products_per_invoice'] = $values['products_per_invoice'];
            $this->session->params['products_per_invoice_variation'] = $values['products_per_invoice_variation'];
            $this->session->proccessed = 0;

            $this->updateDemoHistory();

            if (@$values['do_not_generate_products']) {
                $this->session->params['products_count'] = 0;
                $this->readProductsToSession();
            } else {
                $this->generateProducts();
            }
            $this->sendRedirect();
        }

        $this->view->form = $form;
        $this->view->content = (string)$form . $this->createDemoHistoryGrid()->render();
        $this->view->display('admin/layout.phtml');
    }
    
    function renderGridTitle($record) {
        return $record->completed ?
                sprintf('<td>%s</td>',
                    ___('You have generated %d demo products and %d demo customers',
                    $record->products_count,
                    $record->user_count)
                ) :
                sprintf('<td>%s</td>',
                    ___('Generation of demo data  was terminated while processing. Not all records were created.')
                );
    }
    public function createDemoHistoryGrid()
    {
        $records = $this->getDi()->store->getBlob('demo-builder-records');
        $records = $records ? unserialize($records) : array();
        $ds = new Am_Grid_DataSource_Array($records);
        $grid = new Am_Grid_Editable('_h', ___("Demo History"), $ds, $this->_request, $this->view);
        $grid->addField(new Am_Grid_Field('date', 'Date', false, '', null, '10%'));
        $grid->addField(new Am_Grid_Field('title', 'Title', false, '', array($this, 'renderGridTitle'), '90%'));
        $grid->actionsClear();
        $grid->actionAdd(new Am_Grid_Action_DemoDel);
        return $grid;
    }

    public function generateUser(& $context, $batch) {
    
        $payplugin = $context['payplugin'];
        $demoBuilder = new DemoBuilder($this->getDi(), $this->getID());    
        
        $user = $demoBuilder->createUser($this->session->params['email_domain']);
        $this->getDi()->hook->call(Am_Event::BUILD_DEMO, array(
            'user' => $user,
            'demoId' => $this->getID(),
            'usersCreated' => $this->session->processed,
            'usersTotal' => $this->session->params['users_count'],
        ));
        $user->save();

        $demoBuilder->createInvoices($user,
                $payplugin,
                $this->session->productIds, 
                $this->session->params['invoices_per_user'], 
                $this->session->params['invoices_per_user_variation'], 
                $this->session->params['products_per_invoice'], 
                $this->session->params['products_per_invoice_variation']);

        $user = null;
        unset($user);

        $this->session->proccessed++;
        return $this->session->proccessed >= $this->session->params['users_count'];
    }

    public function doAction() 
    {
        // disable all emails
        Am_Mail::setDefaultTransport(new Am_Mail_Transport_Null());
        
        $payplugin = null;
        foreach ($this->getDi()->plugins_payment->getEnabled() as $pl)
        {
            if ($pl == 'free') continue;
            $payplugin = $this->getDi()->plugins_payment->loadGet($pl);
            break;
        }
        if (empty($payplugin))
            throw new Am_Exception_InputError("No payment plugins enabled. Visit [aMember Cp -> Setup/Configuration -> Plugins] and enable one");

        $batch = new Am_BatchProcessor(array($this, 'generateUser'), 5);
        $context = array(
            'payplugin' => $payplugin
        );
        
        if (!$batch->run($context)) {
            $this->sendRedirect();
        }

        $this->updateDemoHistory(true);

        $this->session->unsetAll();
        $this->_redirect('admin-build-demo');
    }

    public function deleteAction() {
        $this->session->unsetAll();
        $this->session->proccessed = 0;
        $this->session->lastUserId = 0;

        $query = new Am_Query(Am_Di::getInstance()->userTable);
        $this->session->total = $query->getFoundRows();

        $this->session->params = array();
        $this->session->params['demo-id'] = $this->getRequest()->getParam('id');

        if (!$this->session->params['demo-id']) {
            throw new Am_Exception_InputError('demo-id is undefined');
        }

        $this->deleteProducts($this->session->params['demo-id']);
        $this->deleteProductCategories($this->session->params['demo-id']);

        $this->sendDelRedirect();
    }

    function deleteUser(& $context, $batch) {
        $count = 10;

        $query = new Am_Query(Am_Di::getInstance()->userTable);
        $query = $query->addOrder('user_id')->addWhere('user_id>?', $this->session->lastUserId);

        $users = $query->selectPageRecords(0, $count);

        $moreToProcess = false;
        foreach ($users as $user) {
            $demoId = $user->data()->get('demo-id');
            $this->session->lastUserId = $user->pk();
            if ($demoId && $demoId == $this->session->params['demo-id']) {
                $user->delete();
            }
            $this->session->proccessed++;
            $moreToProcess = true;
        }

        return !$moreToProcess;
    }

    function doDeleteAction() {  
        $batch = new Am_BatchProcessor(array($this, 'deleteUser'));
        $context = null;

        if (!$batch->run($context)) {
            $this->sendDelRedirect();
        }

        $this->delDemoHistory($this->session->params['demo-id']);

        $this->session->unsetAll();
        $this->_redirect('admin-build-demo');
    }

    protected function updateDemoHistory($completed = false) {
        $records = $this->getDi()->store->getBlob('demo-builder-records');
        $records = $records ? unserialize($records) : array();

        $record = new stdClass();
        $record->date = $this->getDi()->sqlDate;
        $record->user_count = $this->session->proccessed;
        $record->products_count = $this->session->params['products_count'];
        $record->id = $this->getID();
        $record->completed = $completed;

        $records[$this->getID()] = $record;
        $this->getDi()->store->setBlob('demo-builder-records', serialize($records));
    }

    protected function delDemoHistory($demoId) {
        $records = $this->getDi()->store->getBlob('demo-builder-records');
        $records = $records ? unserialize($records) : array();
        unset($records[$demoId]);
        $this->getDi()->store->setBlob('demo-builder-records', serialize($records));
    }

    protected function deleteProducts($demoId) {
        foreach ($this->getDi()->productTable->getOptions() as $product_id => $title) {
            $product = $this->getDi()->productTable->load($product_id);
            $prDemoId = $product->data()->get('demo-id');
            if ($prDemoId == $demoId) {
                $product->delete();
            }
        }
        return;
    }

    protected function deleteProductCategories($demoId) {
        $query = new Am_Query(new ProductCategoryTable);
        $query->add(new Am_Query_Condition_Field('code', 'LIKE', $demoId.':%'));
        $count = $query->getFoundRows() ? $query->getFoundRows() : 1;
        foreach($query->selectPageRecords(0, $count) as $pCategory) {
            $pCategory->delete();
        }
    }

    protected function readProductsToSession() {
        foreach ($this->getDi()->productTable->findBy() as $p) 
        {
            if ($p->getFirstPrice() <= 0) continue;
            $this->session->productIds[$p->product_id] = $p->product_id;
        }
    }

    protected function generateProducts() {     
        $demoBuilder = new DemoBuilder($this->getDi(), $this->getID());
        
        $this->session->productIds = $demoBuilder->createProducts(
                $this->session->params['products_count'], 2);
    }

    protected function sendRedirect() {
        $proccessed = $this->session->proccessed;
        $total = $this->session->params['users_count'];
        $this->redirectHtml($this->getUrl('admin-build-demo', 'do'), ___("Building demo records").". " .___("Please wait")."...", ___("Build Demo"), false, $proccessed, $total);
    }

    protected function sendDelRedirect() {
        $proccessed = $this->session->proccessed;
        $total = $this->session->total;
        $this->redirectHtml($this->getUrl('admin-build-demo', 'do-delete'), ___("Cleaning up").". ". ___("Please wait...")."...", ___("Cleanup"), false, $proccessed, $total);
    }

    protected function getID() {
        if (!$this->session->ID) {
            $this->session->ID = md5(mktime() . rand(0, 999));
        }
        return $this->session->ID;
    }

}

