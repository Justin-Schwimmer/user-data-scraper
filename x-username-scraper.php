<?php
namespace src;

use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverKeys;
use PDO;

require_once('../vendor/autoload.php');
require_once( 'lib/inc/utility-functions.php');

set_time_limit(0);
error_reporting(E_ALL);

## settings
$host           = 'http://localhost:4444'; // this is the default
$capabilities   = DesiredCapabilities::chrome();

## create driver
$driver = RemoteWebDriver::create($host, $capabilities, 5000);

## twitter/x login page
$driver->get('https://x.com/i/flow/login');

## twitter credentials
$acctUserName = '';
$acctEmail    = '';
$acctPassword = '';

sleep( RandomSecondsToWait() );

## successfull login?
if ( Login( $driver, $acctUserName, $acctPassword ) )
{
    ## Click search link: a[@href="/explore"]
    $searchLink = $driver->wait()->until(
        function () use ( $driver ) {
            $searchLink = $driver->findElement( 
                WebDriverBy::xpath("//a[@href='/explore']")
            );
            return $searchLink;
        }
    );
    $searchLink->click();

    sleep( RandomSecondsToWait() );

    ## get inital search phrase
    $searchPhraseData = GetSearchPhrase();

    ## Loop through search phrases
    while( !empty( $searchPhraseData["phrase"] ) )
    {
        $totalUsersPerPhrase = 0;

        ## search input element: //input[@placeholder="Search"]
        $searchElement = $driver->wait()->until( 
            function () use ($driver) {
                $searchElement = $driver->findElement( 
                    WebDriverBy::xpath("//input[@placeholder='Search']") 
                );
                return $searchElement;
            }
        );
        $searchElement->sendKeys( $searchPhraseData["phrase"] );
        $searchElement->sendKeys( WebDriverKeys::RETURN_KEY );

        sleep( RandomSecondsToWait() );

        ## people tab link: a > span[@innerHTML="People"]
        $peopleLink = $driver->wait()->until(
            function () use ( $driver ) {
                $peopleLink = $driver->findElement( 
                    WebDriverBy::xpath("//a/div/div/span[text()='People']")
                );
                return $peopleLink;
            }
        );
        $peopleLink->click();

        sleep( RandomSecondsToWait() );

        /**
         * Loop through button items for the first a tag and grab the link,
         * replace the "/" with "@" then you have the username. Do think 
         * and scroll to the bottom of the page
         * 
         * usernames: //button/div/div[2]/div[1]/div[1]/div/div[2]/div/a/div/div/span
         *   
         */
        try 
        {
            $scollCount = 0;

            while (true) 
            {
                $usernames = $driver->wait()->until(
                    function () use ($driver) {
                        $usernames = $driver->findElements( 
                            WebDriverBy::xpath("//button/div/div[2]/div[1]/div[1]/div/div[2]/div/a/div/div/span")
                        );
                        return $usernames;
                    }
                );
                
                $userCount            = count($usernames);
                
                print( $userCount . ' found' . PHP_EOL);
                
                $totalUsersPerPhrase += DbStoreUsernames( $usernames,
                                                        $searchPhraseData["id"] );
                
                sleep( RandomSecondsToWait() );

                $driver->executeScript('window.scrollBy(0, window.innerHeight)');

                sleep( RandomSecondsToWait() );
                
                $scollCount += 1;
                
                ## reset username collection
                $usernames   = null; 

                print( 'scroll count = ' . $scollCount . PHP_EOL );
                
                ## keep scrolling up to 100 times 
                ## or stop if we've reached the bottom of the page
                if( $scollCount >= 50 )
                {
                    break;
                }
            }

            print( 'Total usernames for Phrase: "' 
                    . $searchPhraseData["phrase"]  . '" = ' . $totalUsersPerPhrase . PHP_EOL );

        } catch (\Throwable $th) {
            print( $th->getMessage() );
        }

        ## update phrase data
        UpdateSearchPhraseStats( $searchPhraseData["id"], $totalUsersPerPhrase );

        ## clear button only shows when text field is active
        ##
        ## TODO: HOW DO I CLEAR THE TEXT
        ##
        ## clear out search field prior to running next phrase
        $clearSearchElement = $driver->wait()->until( 
            function () use ($driver) {
                $searchElement = $driver->findElement( 
                    WebDriverBy::xpath("//input[@placeholder='Search']") 
                );
                
                $searchElement->click();

                $clearSearchElement = $driver->findElement( 
                    WebDriverBy::xpath("//button[@aria-label='Clear']") 
                );

                return $clearSearchElement;
            }
        );
        $clearSearchElement->click(); 

        $searchPhraseData = GetSearchPhrase();
    }
}

## Close the driver
$driver->quit();

/**
 * Store aquired usernames
 **/
function DbStoreUsernames( $usernames, $phraseId ) 
{
    try {
        ## set up prepared stmt
        $pdo = GetDBConnection();
    
        $pdoStmt = $pdo->prepare( 'INSERT INTO users (`phrase_id`,`username`) 
                                    VALUES ( :phrase_id, :username )' );

        ## ignore errors due to unique usernames constraint
        $usernamesAdded = 0;
        foreach ($usernames as $username) 
        {
            try {
                @$pdoStmt->execute( ['phrase_id' => $phraseId, 
                                     'username' => $username->getText() ] );
                $usernamesAdded++;
            } catch (\Throwable $th) {
                #### 
                ## IGNORE UNIQUE RESTRAINT ERRORS, THIS IS TO AVOID ADDING DUPES
                ## 
                ## print( 'Error Storing Usernames '. $th->getMessage() . 'moving on to next username' . PHP_EOL);
                continue;
            }
        }                        

        print( $usernamesAdded . ' usernames saved' . PHP_EOL );

    } catch (\Throwable $th) {
        print( 'Error Storing Usernames '. $th->getMessage());
    }

    return $usernamesAdded;
}

/**
 * Obtain the search phrase to process
 */
function GetSearchPhrase() 
{
    $dbh = GetDBConnection();
    
    $res = $dbh->query("SELECT id, phrase FROM phrases 
                        WHERE is_completed = '0' LIMIT 1", PDO::FETCH_ASSOC);

    $phraseData = $res->fetch();
    
    if( empty($phraseData) ) { $phraseData == false; }

    return $phraseData;
}

/**
 * Upon completion, update stats for search phrase
 */
function UpdateSearchPhraseStats( $phraseId, $totalUsersPerPhrase ) 
{
    $dbh = GetDBConnection();
    
    $dbh->query("UPDATE phrases 
                SET is_completed = '1',
                    total_users  = '" . $totalUsersPerPhrase . "' "
                .  'WHERE id = ' . $phraseId );
}
