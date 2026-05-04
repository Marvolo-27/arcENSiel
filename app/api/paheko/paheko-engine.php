<?php
// extract auth token
$input = json_decode(file_get_contents("php://input"), true);
$csrf = $input['csrf'] ?? null;
$action = $action ?? null;
$datapath = $root.'/app/api/paheko/data/';

// Restrict to admins
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo "<h1>Unauthorized</h1>The requested ressource is not accessible.";
	exit;
}

if ($_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo "<h1>Forbidden</h1>You don't have permission to access this resource.";
	exit;
}

if ($_SESSION['CSRF'] !== $csrf || !$csrf) {
    http_response_code(401);
    echo "<h1>Unauthorized</h1>The requested ressource is not accessible.";
	exit;
}

// init files
$logfile = fopen($root.'/log/paheko-curl.log', 'a+');

// Init curl
$ch = curl_init();
curl_setopt($ch, CURLOPT_STDERR, $logfile);								            // file to receive request errors output.
curl_setopt($ch, CURLOPT_VERBOSE, true);											// output détaillé.
curl_setopt($ch, CURLOPT_USERPWD, "$paheko_client_id:$paheko_client_secret");		// api credentials
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);	                                    // maximum allowed connection time
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);	                                    // gives response output as return content, instead of printing directly - oveerides the default booolean output

function curlQuery($slug, $file_object=false, $postData=false) {
    global $ch;
    global $paheko_address;
    global $root;

    curl_setopt($ch, CURLOPT_URL, $paheko_address.$slug);	// request url

    if ($file_object) {// export query response body to file
        curl_setopt($ch, CURLOPT_FILE, $file_object);		// file output
    }

    if ($postData) {// post query
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    }

    try {
        ini_set('memory_limit', '512M');
        set_time_limit(0); // extend script execution no limit to handle slow download speed
        $response = curl_exec($ch);
        set_time_limit(30);
    } catch (Exception $e) {
        return ['succes' => false, 'error' => '[ERROR] Failed: '.$e];
    }

    if ($file_object) {
        fclose($file_object);
    }

    if (curl_errno($ch)) {
        //curl_close($ch);
		return ['succes' => false, 'error' => '[ERROR] HTTP code: '.curl_getinfo($ch, CURLINFO_HTTP_CODE), 'code' => curl_getinfo($ch, CURLINFO_HTTP_CODE)];
	}

    //curl_close($ch);
    return ['success' => true, 'data' => json_decode($response, true)];
}


if ($action === 'update') {
    // Fetch categories
    $fetch = curlQuery('/user/categories');

    if (!$fetch['success']) {
        http_response_code(($fetch['code']));
        echo json_encode(['error' => '[Paheko API] Failed fetching categories.']);
        logger('/log/paheko.log', "Failed fetching list of categories.", '[ERROR] Failed: fetch categories');
        exit;
    } else {
        $categories = $fetch['data'];
        logger('/log/paheko.log', "Paheko API categories fetched.", 'Fetch categories');
    }

    // fetch services
    $query = ['format' => 'json', 'sql' => 'SELECT * FROM services ORDER BY start_date DESC'];
    $fetch = curlQuery('/sql', false, $query);

    if (!$fetch['success']) {
        http_response_code(($fetch['code']));
        echo json_encode(['error' => '[Paheko API] Failed fetching services.']);
        logger('/log/paheko.log', "Failed fetching list of services.", '[ERROR] Failed: fetch services');
        exit;
    } else {
        $services = $fetch['data']['results'];
        logger('/log/paheko.log', "Paheko API services fetched.", 'Fetch services');
    }

    // list of users in each category - i suppose it doesn't have built-in limit like /sql query (not mentionned anyway)
    $composition = [];
    foreach ($categories as $cat) {
        $fetch = curlQuery("/user/category/".$cat['id'].".json");

        if (!$fetch['success']) {
            http_response_code(($fetch['code']));
            echo json_encode(['error' => "[Paheko API] Failed fetching members of category '".$cat['name']."'."]);
            logger('/log/paheko.log', "Failed fetching members of category '".$cat['name']."'.", '[ERROR] Failed: fetch category content');
            exit;
        } else {
            $wk_category = $fetch['data'];
            $composition = array_merge($composition, $wk_category);
            logger('/log/paheko.log', "Members of category '".$cat['name']."' fetched", 'Fetch category content');
        }
    }

    // get nb of users to handle paheko auto limit
    $query = ['format' => 'json', 'sql' => 'SELECT COUNT(*) AS amount FROM users'];
    $fetch = curlQuery('/sql', false,  $query);
    $total = $fetch['success'] ? $fetch['data']['results'][0]['amount']:1000;
    $looper = ceil($total/1000);

    // list of users in each category - i suppose it doesn't have built-in limit like /sql query (not mentionned anyway)
    $users = [];
    for ($i=1;$i<=$looper;$i++) {// loop sql byè pack of 1,000 entries
        $query = ['format' => 'json', 'sql' => 'SELECT users.id, nom, email, telephone, date_inscription, label, date, expiry_date FROM services JOIN services_users ON services.id = id_service JOIN users ON users.id = id_user ORDER BY users.id'.' LIMIT '.($i * 1000).' OFFSET '.(($i - 1)*1000)];
         $fetch = curlQuery('/sql', false,  $query);

        if (!$fetch['success']) {
            http_response_code(($fetch['code']));
            echo json_encode(['error' => "[Paheko API] Failed fetching services members ".(($i - 1)*1000 + 1).'-'.($i*1000)."."]);
            logger('/log/paheko.log', "Failed fetching services members ".(($i - 1)*1000 + 1).'-'.($i*1000).".", '[ERROR] Failed: fetch services members');
            exit;
        } else {
            $wk_user = $fetch['data']['results'];
            $users = array_merge($users, $wk_user);
            logger('/log/paheko.log', "Services members ".(($i - 1)*1000 + 1).'-'.($i*1000)." fetched.", 'Fetch services members');
        }
    }

    // fetch goodies stocks
    $query = ['format' => 'json', 'sql' => "SELECT plugin_pos_products.name, description, price, purchase_price, stock FROM plugin_pos_categories JOIN plugin_pos_products ON category = plugin_pos_categories.id WHERE plugin_pos_categories.name = 'Goodies'"];
    $fetch = curlQuery('/sql', false, $query);

    if (!$fetch['success']) {
        http_response_code(($fetch['code']));
        echo json_encode(['error' => '[Paheko API] Failed fetching goodies.']);
        logger('/log/paheko.log', "Failed fetching list of goodies.", '[ERROR] Failed: fetch goodies');
        exit;
    } else {
        $goodies = $fetch['data']['results'];
        logger('/log/paheko.log', "Paheko API goodies fetched.", 'Fetch goodies');
    }


    $output = ['categories' => $categories, 'services' => $services, 'composition' => $composition, 'users' => $users, 'goodies' => $goodies];
    $outputJSON = json_encode($output, JSON_PRETTY_PRINT);

    file_put_contents(__DIR__ . "/data/all.json", $outputJSON);
    file_put_contents(__DIR__ . "/data/categories.json", json_encode($categories, JSON_PRETTY_PRINT));
    file_put_contents(__DIR__ . "/data/services.json", json_encode($services, JSON_PRETTY_PRINT));
    file_put_contents(__DIR__ . "/data/composition.json", json_encode($composition, JSON_PRETTY_PRINT));
    file_put_contents(__DIR__ . "/data/users.json", json_encode($users, JSON_PRETTY_PRINT));
    file_put_contents(__DIR__ . "/data/goodies.json", json_encode($goodies, JSON_PRETTY_PRINT));
    logger('/log/paheko.log', "Data synchronized from Paheko API.", 'Fetch Paheko data');

    http_response_code(200);
    echo $outputJSON;
    exit;
}

if ($action === 'download') {
    $type = $input['type'];
    $date = date("Y-m-d_H.i.s", time());
    $mode = $input['mode'] ?? 'basic';

    // choose file to download
    if ($type === 'database') {
        $filename = "paheko-db-backup_$date.sqlite";
        $url = '/download';
        $description = 'Sauvegarde de la base de données de Paheko.';
        $mime = 'application/vnd.sqlite3';
    } elseif ($type === 'archive') {
        $filename = "paheko-archive-backup_$date.zip";
        $url = '/download/files';
        $description = 'Archive des fichiers stockés sur Paheko.';
        $mime = 'application/zip';
    } else {
        http_response_code(400);
        echo json_encode(['error' => "Invalid download request: No type $type."]);
        exit;
    }
    
    // download file
    $file = fopen($datapath.$filename, 'w');
    $fetch = curlQuery($url,  $file);

    if (!$fetch['success']) {
        http_response_code(($fetch['code']));
        echo json_encode(['error' => "[Paheko API] Failed downloading $type.", 'available' => false]);
        logger('/log/paheko.log', "Failed downloading $type file.", "[ERROR] Failed: download $type.");
        exit;
    } else {
        logger('/log/paheko.log', "Paheko API $type file downloaded on server.", "Download $type.");
    }

    // prepare sendback
    $source = file_get_contents($datapath.$filename);
    $base64 = base64_encode($source);

    // leave if no backup intended
    if ($mode !== 'backup') {
        // remove downloaded file
        unlink($datapath.$filename);
        logger('/log/files.log', "Paheko backup file removed from server.", 'Remove file');;

        // sendback
        http_response_code(200);
        echo json_encode(['message' => 'Data fetched successfully from Paheko.', 'available' => true, 'data' => ['type' => $mime, 'content' => $base64, 'name' => $filename]]);
        exit;
    }

    // move downloaded file to its end destination
    $dest = $root."/app/admin/files/backup/$filename";
	if (rename($datapath.$filename, $dest)) {
		logger('/log/files.log', "Paheko backup file moved to: /app/admin/files/backup/$filename", 'Move file');
	} else {
		http_response_code(500);
		echo json_encode(['error' => "Backup failed: could not move file to '/app/admin/files/backup/$filename'", 'available' => true, 'data' => ['type' => $mime, 'content' => $base64, 'name' => $filename]]);
		logger('/log/files.log', "Paheko backup file could not be moved to '/app/admin/files/backup/$filename'", 'Failed: move file');
		exit;
    }

    // add file entry if required
    if ($mode === 'backup') {
        $sql = "INSERT INTO files (name, url, used_by, size, description, type, status, uploader, category, subcategory) VALUES (:name, :url, :reference, :size, :desc, :type, TRUE, :user, 'sauvegarde', '$type')"; // safe bc $type value already tested
        $params = [
                ':name' => $filename,
                ':url' => "admin:///files/backup/$filename",
                ':reference' => null,
                ':size' => filesize($dest),
                ':desc' => $description,
                ':type' => $mime,
                ':user' => $_SESSION['username'],
            ];

        $query = $pdo->prepare($sql);
        foreach ($params as $param => $value) {
            $query->bindValue($param, $value, $param === ':size' ? PDO::PARAM_INT:PDO::PARAM_STR);
        }
        try {
            $query->execute();
            logger('/log/files.log', queryMaker($sql, $params), 'Insert file in db');
        } catch (PDOException $e) {
            http_response_code(250); // access authorized so success but failed to ouput sql
            echo json_encode( ['code' => 500, 'error' => json_encode($e), 'available' => true, 'data' => ['type' => $mime, 'content' => $base64, 'name' => $filename]]);
            logger('/log/files.log', json_encode($e), '[ERROR] Failed: insert file in db');
            exit;
        }
    }

   // sendback
    http_response_code(200);
    echo json_encode(['message' => 'Data fetched successfully from Paheko.', 'available' => true, 'data' => ['type' => $mime, 'content' => $base64, 'name' => $filename]]);
    exit;
}