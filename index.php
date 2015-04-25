<?php

require "config.php";
require "Database.php";
require "Seed.php";
require "Stock.php";

$db = Database::getInstance();

$nasdaq_url = "ftp://ftp.nasdaqtrader.com/symboldirectory/nasdaqlisted.txt";
$nyse_url = "http://www.nasdaq.com/screening/companies-by-name.aspx?letter=0&exchange=nyse&render=download";

$nasdaq_file = "stock-cache/nasdaq.cache";
$nyse_file = "stock-cache/nyse.cache";

function getStockInfo($url, $file)
{
    if (file_exists($file) && (time() - filemtime($file) <= 60 * 60 * 24))
    {
        $nyse_content = file_get_contents($file);
    }
    else
    {
        unlink($file);
        $nyse_content = file_get_contents($url);
        file_put_contents($file, $nyse_content);
    }

    return $nyse_content;
}

if (!isset($_GET['query']))
{
    echo "Invalid query.";
    die();
}

if ($_GET['query'] === "list")
{
    $market = (isset($_GET['market'])) ? $_GET['market'] : "all";

    $nasdaq_json = array();
    $nyse_json = array();

    //
    // NASDAQ Handling
    //

    if ($market === "all" || $market === "nasdaq")
    {
        $nasdaq = explode(PHP_EOL, getStockInfo($nasdaq_url, $nasdaq_file));
        $nasdaq = array_slice($nasdaq, 1, -6);

        foreach ($nasdaq as $stock)
        {
            $stock_info = explode("|", $stock);
            $stock_name = explode(" - ", $stock_info[1]);

            $nasdaq_json[] = array(
                "symbol" => $stock_info[0],
                "name" => $stock_name[0]
            );
        }
    }

    //
    // NYSE Handling
    //

    if ($market === "all" || $market === "nyse")
    {
        $nyse = explode(PHP_EOL, getStockInfo($nyse_url, $nyse_file));
        array_shift($nyse);

        foreach ($nyse as $stock_summary)
        {
            $stock = explode("\",\"", $stock_summary);

            if (count($stock) >= 2)
            {
                $nyse_json[] = array(
                    "symbol" => str_replace("\"", "", $stock[0]),
                    "name" => html_entity_decode($stock[1])
                );
            }
        }
    }

    //
    // Output
    //

    header('Content-Type: application/json');

    if ($market === "all")
    {
        echo json_encode(array_merge($nyse_json, $nasdaq_json));
    }
    else if ($market === "nyse")
    {
        echo json_encode($nyse_json);
    }
    else if ($market === "nasdaq")
    {
        echo json_encode($nasdaq_json);
    }
    else if ($market === "coral")
    {
        echo json_encode(Stock::getStocks());
    }
}
else if ($_GET['query'] === "stock")
{
    if (!isset($_GET['symbol']))
    {
        echo "Invalid query. Missing stock symbol.";
        die();
    }

    $myStock = new Stock($_GET['symbol']);

    $stock = array(
        "symbol" => $myStock->getSymbol(),
        "name" => $myStock->getName(),
        "trades" => $myStock->getTrades()
    );

    header('Content-Type: application/json');

    echo json_encode($stock);
}
else if ($_GET['query'] === "create")
{
    if (!isset($_GET['symbol']) || !isset($_GET['name']))
    {
        echo "Invalid query. Missing stock symbol and/or name.";
        die();
    }

    if (!isset($_GET['key']) || $_GET['key'] != CREATE_KEY)
    {
        echo "Invalid request.";
        die();
    }

    Stock::createStock($_GET['symbol'], $_GET['name']);

    $url = strtok($_SERVER["REQUEST_URI"],'?');

    header('location: ' . $url . '?query=stock&symbol=' . $_GET['symbol']);
}