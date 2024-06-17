<?php

use Facebook\WebDriver\WebDriverBy;

function Login( $driver, $acctUserName, $acctPassword ) 
{
    try {
        sleep( RandomSecondsToWait() );

        ## username element: //input[@autocomplete="username"]
        $usernameElement = $driver->wait()->until( 
            function () use ($driver) {
                $usernameElement = $driver->findElement( 
                    WebDriverBy::xpath( '//input[@autocomplete="username"]' 
                ) );
                return $usernameElement;
            }
        );

        $usernameElement->sendKeys( $acctUserName );

        sleep( RandomSecondsToWait() );

        ## click next button: button > span with NEXT text
        $nextButton = $driver->wait()->until( 
            function () use ($driver) {
                $nextButton = $driver->findElement( 
                    WebDriverBy::xpath("//button/div/span/span[text()='Next']") 
                );
                return $nextButton;
            }
        );
        $nextButton->click();

        sleep( RandomSecondsToWait() );

        ## password element: //input[@name="password"]
        $passwordElement = $driver->wait()->until( 
            function () use ($driver) {
                $passwordElement = $driver->findElement( 
                    WebDriverBy::xpath("//input[@name='password']") 
                );
                return $passwordElement;
            }
        );
        $passwordElement->sendKeys( $acctPassword );

        sleep( RandomSecondsToWait() );

        ## click password log in button: 
        $loginButton = $driver->wait()->until(
            function () use ( $driver ) {
                $loginButton = $driver->findElement( 
                    WebDriverBy::xpath("//button/div/span/span[text()='Log in']")
                );
                return $loginButton;
            }
        );
        $loginButton->click();

        sleep( RandomSecondsToWait() );

    } catch (\Throwable $th) {
        return false;
    }

    return true;
}

function RandomSecondsToWait() {
    return rand(4,7);
}

function GetDBConnection() 
{
    $dsn = 'mysql:dbname=x_usernames;host=127.0.0.1';
    $user = 'root';
    $password = 'toydoll';

    try {
        $pdo = new PDO($dsn, $user, $password);
    } catch (\Throwable $th) {
        print( ' DB Con error ' . $th->getMessage() );
    }

    return $pdo;
}