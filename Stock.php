<?php

class Stock
{
    private $db;

    private $id;
    private $symbol;
    private $name;
    private $original_price;
    private $closing_price;
    private $valid;

    public function __construct($symbol)
    {
        $this->db = Database::getInstance();

        $stock = $this->db->prepare("SELECT * FROM stocks WHERE symbol = ? LIMIT 1");
        $stock->execute(array($symbol));

        $rows = $stock->fetchAll(PDO::FETCH_ASSOC);

        if (count($rows) != 1)
        {
            $this->valid = false;
            return;
        }

        $this->id = $rows[0]['id'];
        $this->symbol = $rows[0]['symbol'];
        $this->name = $rows[0]['name'];
        $this->original_price = $rows[0]['original_price'];
        $this->valid = true;

        try
        {
            $closing_price = $this->db->prepare("SELECT close FROM `values` WHERE stock = ? ORDER BY `date` DESC LIMIT 1");
            $closing_price->execute(array($this->id));

            $rows = $closing_price->fetchAll(PDO::FETCH_ASSOC);

            $this->closing_price = (count($rows) > 0) ? $rows[0]['close'] : $this->original_price;
        }
        catch(Exception $e)
        {
            $this->closing_price = $this->original_price;
        }

        $this->closing_price = floatval($this->closing_price);
    }

    public function getID()
    {
        return $this->id;
    }

    public function getSymbol()
    {
        return $this->symbol;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getOriginalPrice()
    {
        return $this->original_price;
    }

    public function getClosingPrice()
    {
        return $this->closing_price;
    }

    public function setClosingPrice($price)
    {
        $this->closing_price = $price;
    }

    public function getTrades()
    {
        $sql = "SELECT * FROM `values` WHERE `date` >= (NOW() - interval 366 day) AND stock = ?";
        $trades = $this->db->prepare($sql);
        $trades->execute(array($this->id));

        $rows = $trades->fetchAll(PDO::FETCH_ASSOC);

        $tradeArray = array();

        foreach ($rows as $row)
        {
            $tradeArray[] = floatval($row['close']);
        }

        return $tradeArray;
    }

    public function isValid()
    {
        return $this->valid;
    }

    public function setClosePrice($date)
    {
        $close = $this->calculateNewPrice();

        $sql = "INSERT IGNORE INTO `values` VALUES (NULL, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array($this->getID(), $date, $this->getClosingPrice(), $close));

        $this->setClosingPrice($close);
    }

    // Inverse ncdf approximation by Peter John Acklam, implementation adapted to
    // PHP by Michael Nickerson, using Dr. Thomas Ziegler's C implementation as
    private function inverse_ncdf($p)
    {
        $a = array(1 => -3.969683028665376e+01, 2 => 2.209460984245205e+02,
                   3 => -2.759285104469687e+02, 4 => 1.383577518672690e+02,
                   5 => -3.066479806614716e+01, 6 => 2.506628277459239e+00);

        $b = array(1 => -5.447609879822406e+01, 2 => 1.615858368580409e+02,
                   3 => -1.556989798598866e+02, 4 => 6.680131188771972e+01,
                   5 => -1.328068155288572e+01);

        $c = array(1 => -7.784894002430293e-03, 2 => -3.223964580411365e-01,
                   3 => -2.400758277161838e+00, 4 => -2.549732539343734e+00,
                   5 => 4.374664141464968e+00, 6 => 2.938163982698783e+00);

        $d = array(1 => 7.784695709041462e-03, 2 => 3.224671290700398e-01,
                   3 => 2.445134137142996e+00, 4 => 3.754408661907416e+00);

        $p_low =  0.02425;
        $p_high = 1 - $p_low;

        $q = NULL; $x = NULL; $y = NULL; $r = NULL;

        if (0 < $p && $p < $p_low)
        {
            $q = sqrt(-2 * log($p));
            $x = ((((($c[1] * $q + $c[2]) * $q + $c[3]) * $q + $c[4]) * $q + $c[5]) *
                $q + $c[6]) / (((($d[1] * $q + $d[2]) * $q + $d[3]) * $q + $d[4]) *
                $q + 1);
        }
        elseif ($p_low <= $p && $p <= $p_high)
        {
            $q = $p - 0.5;
            $r = $q * $q;
            $x = ((((($a[1] * $r + $a[2]) * $r + $a[3]) * $r + $a[4]) * $r + $a[5]) *
                $r + $a[6]) * $q / ((((($b[1] * $r + $b[2]) * $r + $b[3]) * $r +
                $b[4]) * $r + $b[5]) * $r + 1);
        }
        elseif ($p_high < $p && $p < 1)
        {
            $q = sqrt(-2 * log(1 - $p));
            $x = -((((($c[1] * $q + $c[2]) * $q + $c[3]) * $q + $c[4]) * $q +
                $c[5]) * $q + $c[6]) / (((($d[1] * $q + $d[2]) * $q + $d[3]) *
                $q + $d[4]) * $q + 1);
        }
        else {
            $x = NULL;
        }

        return $x;
    }

    private function calculateNewPrice($sigma = 0.07, $mu = 0.1, $dt = 0.01)
    {
        $rand = self::getDecimal();
        $drift = $mu * $dt * $this->closing_price;
        $uncertainty = $this->inverse_ncdf($rand) * sqrt($dt) * $sigma * $this->closing_price;
        $change = $uncertainty + $drift;
        $newPrice = $this->closing_price + $change;

        return $newPrice;
    }

    private static function getDecimal()
    {
        return mt_rand() / mt_getrandmax();
    }

    public static function createStock($symbol, $name)
    {
        if ((new Stock($symbol))->isValid())
        {
            return;
        }

        $dbc = Database::getInstance();
        $startingPrice = mt_rand(22, 78) + self::getDecimal();

        $sql = "INSERT INTO `stocks` VALUES (NULL, ?, ?, ?)";
        $stmt = $dbc->prepare($sql);
        $stmt->execute(array($name, strtoupper($symbol), $startingPrice));

        $Stock = new Stock($symbol);

        // Populate the database
        $yearAgo = strtotime("-1 year", time());

        $date = date("Y-m-d", $yearAgo);
        $end_date = date("Y-m-d", time());

        while (strtotime($date) <= strtotime($end_date))
        {
            $Stock->setClosePrice($date);

            $date = date ("Y-m-d", strtotime("+1 day", strtotime($date)));
        }
    }

    public static function getStocks()
    {
        $dbc = Database::getInstance();

        $sql = "SELECT * FROM `stocks`";
        $fakeStocks = $dbc->prepare($sql);
        $fakeStocks->execute();

        $rows = $fakeStocks->fetchAll(PDO::FETCH_ASSOC);

        $coral_exchange = array();

        foreach ($rows as $row)
        {
            $coral_exchange[] = array(
                "symbol" => $row['symbol'],
                "name" => $row['name']
            );
        }

        return $coral_exchange;
    }
}