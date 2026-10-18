<?php require __DIR__.'/../app/bootstrap.php';
$pdo=App\Core\Database::connection();
foreach(['items','locations','devices'] as $t){
  $s=$pdo->query('SHOW COLUMNS FROM '.$t);
  echo $t.': '.implode(', ', array_column($s->fetchAll(PDO::FETCH_ASSOC),'Field'))."\n\n";
}
