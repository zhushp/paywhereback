<?php
// Use Loader() to autoload our model
$loader = new \Phalcon\Loader();

$loader->registerDirs(array(
    __DIR__ . '/models/'
))->register();

$di = new \Phalcon\DI\FactoryDefault();

//Set up the database service
$di->set('db', function(){
    return new \Phalcon\Db\Adapter\Pdo\Mysql(array(
        "host" => "localhost:3307",
        "username" => "root",
        "dbname" => "paywheretest"
    ));
});

//Create and bind the DI to the application
$app = new \Phalcon\Mvc\Micro($di);

//Retrieves all products
$app->get('/product', function() use ($app)  {
	$phql = "SELECT * FROM product";
    $items = $app->modelsManager->executeQuery($phql);

    $data = array();
    foreach ($items as $item) {
        $data[] = array(
			'id' => $item->id,
			'productName' => $item->productName,
			'productDescription' => $item->productDescription,
			'urlThumb' => $item->urlThumb,
			'originalPrice' => $item->originalPrice,
			'productPrice' => $item->productPrice,
        );
    }

    echo json_encode($data);
});

//Retrieves all items in cart
$app->get('/cart', function() use ($app)  {
	$phql = "SELECT cart.id, productId, productName, productDescription, urlThumb, originalPrice, productPrice, quantity FROM cart, product WHERE productId = product.id";
    $items = $app->modelsManager->executeQuery($phql);

    $data = array();
    foreach ($items as $item) {
        $data[] = array(
			'id' => $item->id,
            'productId' => $item->productId,
			'productName' => $item->productName,
			'productDescription' => $item->productDescription,
			'urlThumb' => $item->urlThumb,
			'originalPrice' => $item->originalPrice,
			'productPrice' => $item->productPrice,
			'quantity' => $item->quantity,
        );
    }

    echo json_encode($data);
});

//Retrieves number of items in cart
$app->get('/cart/quantity', function() use ($app)  {
	$phql = "SELECT SUM(quantity) AS TotalQuantity FROM cart";
    $items = $app->modelsManager->executeQuery($phql);

	$data = array(
		'TotalQuantity' => number_format($items[0]->TotalQuantity, 0, '.', ''),
	);
	
    echo json_encode($data);
});

//Retrieves total price of all items in cart
$app->get('/cart/price', function() use ($app)  {
	$phql = "SELECT productPrice, quantity FROM cart, product WHERE productId = product.id";
    $items = $app->modelsManager->executeQuery($phql);

	$price = 0;
    foreach ($items as $item) {
        $price += $item->productPrice*$item->quantity;
    }
	
	$data = array(
		'price' => number_format((float)$price, 2, '.', ''),
	);
	
    echo json_encode($data);
});

//Adds items to cart
$app->post('/cart/add_item', function() use ($app) {

    $item = $app->request->getJsonRawBody();
	
	$phql = "SELECT * FROM cart WHERE productId = :productId:";
    $dbitem = $app->modelsManager->executeQuery($phql, array(
        'productId' => $item->productId
	));
	if(count($dbitem)==0){
		$phql = "INSERT INTO cart (productId, quantity) VALUES (:productId:, :quantity:)";
	
		$status = $app->modelsManager->executeQuery($phql, array(
			'productId' => $item->productId,
			'quantity' => $item->quantity
		));
	}else{
		$phql = "UPDATE cart SET quantity = :quantity: WHERE productId = :productId:";
	
		$status = $app->modelsManager->executeQuery($phql, array(
			'quantity' => $item->quantity + $dbitem[0]->quantity,
			'productId' => $item->productId
		));
		
	}

    //Create a response
    $response = new Phalcon\Http\Response();

    //Check if the insertion was successful
    if ($status->success() == true) {

        //Change the HTTP status
        $response->setStatusCode(201, "Created");

        $item->id = $status->getModel()->id;

        $response->setJsonContent(array('status' => 'OK', 'data' => $item));

    } else {

        //Change the HTTP status
        $response->setStatusCode(409, "Conflict");

        //Send errors to the client
        $errors = array();
        foreach ($status->getMessages() as $message) {
            $errors[] = $message->getMessage();
        }

        $response->setJsonContent(array('status' => 'ERROR', 'messages' => $errors));
    }

    return $response;
});

//Removes items from cart
$app->post('/cart/remove_item', function() use ($app) {
	
	$item = $app->request->getJsonRawBody();

    $phql = "DELETE FROM cart WHERE id = :id:";
    $status = $app->modelsManager->executeQuery($phql, array(
        'id' => $item->id
    ));

    //Create a response
    $response = new Phalcon\Http\Response();

    if ($status->success() == true) {
        $response->setJsonContent(array('status' => 'OK'));
    } else {

        //Change the HTTP status
        $response->setStatusCode(409, "Conflict");

        $errors = array();
        foreach ($status->getMessages() as $message) {
            $errors[] = $message->getMessage();
        }

        $response->setJsonContent(array('status' => 'ERROR', 'messages' => $errors));

    }

    return $response;
});

//Update item quantity
$app->post('/cart/update_item', function() use ($app) {

    $item = $app->request->getJsonRawBody();
	
	$phql = "SELECT * FROM cart WHERE id = :id:";
    $dbitem = $app->modelsManager->executeQuery($phql, array(
        'id' => $item->id
	));
	
	if(count($dbitem)==1){
		$phql = "UPDATE cart SET quantity = :quantity: WHERE id = :id:";
	
		$status = $app->modelsManager->executeQuery($phql, array(
			'quantity' => $item->quantity,
			'id' => $item->id
		));
		
	}

    //Create a response
    $response = new Phalcon\Http\Response();

    //Check if the insertion was successful
    if ($status->success() == true) {

        //Change the HTTP status
        $response->setStatusCode(201, "Created");

        $item->id = $status->getModel()->id;

        $response->setJsonContent(array('status' => 'OK', 'data' => $item));

    } else {

        //Change the HTTP status
        $response->setStatusCode(409, "Conflict");

        //Send errors to the client
        $errors = array();
        foreach ($status->getMessages() as $message) {
            $errors[] = $message->getMessage();
        }

        $response->setJsonContent(array('status' => 'ERROR', 'messages' => $errors));
    }

    return $response;
});

$app->handle();