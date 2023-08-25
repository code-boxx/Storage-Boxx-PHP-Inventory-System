<?php
class Items extends Core {
  // (A) ADD OR EDIT ITEM
  //  $sku : item SKU
  //  $name : item name
  //  $unit : item unit
  //  $low : low stock quantity monitor
  //  $desc : item description
  //  $osku : old SKU, for editing only
  function save ($sku, $name, $unit, $low=0, $desc=null, $osku=null) {
    // (A1) CHECK SKU
    $checkSKU = $osku==null ? $sku : $osku ;
    $check = $this->get($sku);
    if ($osku==null && is_array($check) ||
       ($osku!=null && ($sku!=$osku && is_array($check)))) {
      $this->error = "$sku is already registered.";
      return false;
    }

    // (A2) AUTO COMMIT OFF
    $this->DB->start();

    // (A3) ADD ITEM
    if ($osku===null) {
      $this->DB->insert("items", 
        ["item_sku", "item_name", "item_desc", "item_unit", "item_low"],
        [$sku, $name, $desc, $unit, $low]
      );
    }

    // (A4) UPDATE ITEM
    else {
      $this->DB->update(
        "items", ["item_sku", "item_name", "item_desc", "item_unit", "item_low"], 
        "`item_sku`=?", [$sku, $name, $desc, $unit, $low, $osku]
      );
      if ($sku!=$osku) {
        $this->DB->update("item_mvt", ["item_sku"], "`item_sku`=?", [$sku, $osku]);
        $this->DB->update("item_batches", ["item_sku"], "`item_sku`=?", [$sku, $osku]);
        $this->DB->update("suppliers_items", ["item_sku"], "`item_sku`=?", [$sku, $osku]);
      }
    }

    // (A5) COMMIT & RETURN RESULT
    $this->DB->end();
    return true;
  }

  // (B) IMPORT ITEM
  function import ($sku, $name, $unit, $low=0, $desc=null) {
    // (B1) CHECK
    if (is_array($this->get($sku))) {
      $this->error = "$sku is already registered.";
      return false;
    }

    // (B2) SAVE
    return $this->save($sku, $name, $unit, $low, $desc);
  }
  
  // (C) DELETE ITEM
  // WARNING : STOCK MOVEMENT + BATCHES WILL BE REMOVED AS WELL
  //  $sku : item SKU
  function del ($sku) {
    $this->DB->start();
    $this->DB->delete("items", "`item_sku`=?", [$sku]);
    $this->DB->delete("item_mvt", "`item_sku`=?", [$sku]);
    $this->DB->delete("item_batches", "`item_sku`=?", [$sku]);
    $this->DB->end();
    return true;
  }

  // (D) GET/CHECK ITEM BY SKU
  //  $sku : item SKU
  function get ($sku) {
    return $this->DB->fetch("SELECT * FROM `items` WHERE `item_sku`=?", [$sku]);
  }

  // (E) GET OR SEARCH ITEMS
  //  $search : optional search term
  //  $page : optional current page
  function getAll ($search=null, $page=null) {
    // (E1) PARITAL ITEMS SQL + DATA
    $sql = "FROM `items`";
    $data = null;
    if ($search!=null) {
      $sql .= " WHERE `item_sku` LIKE ? OR `item_name` LIKE ? OR `item_desc` LIKE ?";
      $data = ["%$search%", "%$search%", "%$search%"];
    }

    // (E2) PAGINATION
    if ($page != null) {
      $this->Core->paginator(
        $this->DB->fetchCol("SELECT COUNT(*) $sql", $data), $page
      );
      $sql .= $this->Core->page["lim"];
    }

    // (E3) RESULTS
    return $this->DB->fetchAll("SELECT * $sql", $data, "item_sku");
  }

  // (F) CHECK IF VALID SKU
  //  $sku : item SKU
  function check ($sku) {
    return ($this->DB->fetchCol(
      "SELECT `item_sku` FROM `items` WHERE `item_sku`=?", [$sku]
    )) != null ;
  }

  // (G) GET MONITORED ITEMS
  function getMonitor () {
    return $this->DB->fetchAll( "SELECT * FROM `items` WHERE `item_low`>0");
  }
}