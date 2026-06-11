<?php
session_start();
// Server is started with 'public' as root directory.
// Therefore, all direct user-made url requests to files outside public will fail.
// These files can only be reached from server/fetch requests.
// `require` peut uniquement appeler une url de fichier sans paramètres.
// Cependant, require un fichier ne change pas l'URL courante - donc les paramètres sont tjrs accessibles dans le fichier appelé via $_GET

// Define a global constant to detect calls going through router.php :
    // files that exist are rendered directly by the server w/o handling by router.php
    // So APP_ENTRY undefined and allow to force php requests through router
define('APP_ENTRY', true);  


// Import global variables
require_once "config/config.php";

// Parse URL
$raw_request_url = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH); // extrait le chemin de l'URL d'accès à ce fichier (redirigé vers le routeur) SANS paramètres GET
parse_str(parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY), $url_parameters); // extrait les paramètres de requêtes GET (post '?')

// remove poential string-ending '/'
$request_url = preg_match('#.+/$#', $raw_request_url) ? substr($raw_request_url, 0, -1):$raw_request_url;

if ($request_url !== $raw_request_url) {
    header("Location: $request_url");
    exit;
}

// safe-lock admin content and api calls
if (preg_match('#^/(admin|api)#', $request_url)) {
    if (!isset($_SESSION['user_id'])) {// not logged in
        http_response_code(401);
        echo "401: Unauthorized";
        exit;
    } elseif ($_SESSION['role'] !== "admin") {// logged in but no admin
        http_response_code(403);
        echo "403: Forbidden";
        exit;
    }
}

// ignore redirection for media files
if (preg_match('#^/files/#', $request_url, $match)) {
	return false;
    }

// ADMIN ACTIONS
if (preg_match('#^/admin/#', $request_url)) {
    
    // general tools
    if (preg_match('#/tools/.+#', $request_url)) {
        // SQL client
        if (preg_match('#/sql$#', $request_url)) {
            // editmode = edit keyword + any slug
            require $root . '/app/admin/sql-manager.php';
            exit;
        
        // PHP sandbox file
        } elseif (preg_match('#/sandbox$#', $request_url)) {
            // editmode = edit keyword + any slug
            require $root . '/app/admin/sandbox.php';
            exit;
        
        // Article tools
        } elseif (preg_match('#/(create)/(article)#', $request_url, $matches)) {
            $mode = $matches[1];
            $type = $matches[2];

            // unique url of asked article
            $slug = urldecode(end(explode('/', preg_replace("#/admin/tools/$mode/$type#", '', $request_url))));
            
            // reset slug in create mode
            if ($slug) {
                header("Location: /admin/tools/$mode/$type");
                exit;
                }
            
            // fetch manager script
            require $root . "/app/admin/$type-manager.php";
            exit;
        
        // preview article
        } elseif (preg_match('#/(preview)/(article)#', $request_url, $matches)) {
            $slug = urldecode(end(explode('/', preg_replace("#/admin/tools/article/(preview)#", '', $request_url))));

            // prevent empty slugs in edit mode
            if (!$slug) {
                http_response_code(403);
                echo "403: Forbidden";
                exit;
                }
            
            $status = $matches[1];
            $edition = !isset($_SESSION['post-data']);

            require $root . "/app/pages/".$matches[2].".php";
            exit;
        
        // public pages edition
        } elseif (preg_match('#/edit/(documents|ressources)$#', $request_url, $matches)) {
            $request = $matches[1];
            
            // fetch manager script
            $status = 'edit';
            require $root . "/app/pages/$request.php";
            exit;

        // private pages edition
        } elseif (preg_match('#/edit/(article)/(.+)#', $request_url, $matches)) {
            $request = $matches[1];
            $slug = rawurldecode($matches[2]);
            
            // fetch manager script
            $edition = true;
            require $root . "/app/admin/$request-manager.php";
            exit;
        }
    
    // view logs
    } elseif (preg_match('#/logs$#', $request_url)) {
        require $root . '/app/admin/logs-viewer.php';
        exit;
    
    // admin pages requests - manager
    } elseif (preg_match('#/manage/.+#', $request_url)) {
        $slug = end(explode('/', $request_url));
        if (in_array($slug, ['helloasso', 'paheko', 'database', 'files'])) {
            require $root . "/app/admin/$slug-manager.php";
            exit;
        }
    
    // backend tools requests - engine
    } elseif (preg_match('#/engine/.+#', $request_url)) {
        $slug = end(explode('/', $request_url));
        if (in_array($slug, ['article', 'sql', 'database', 'files'])) {
            require $root . "/app/admin/backend/$slug-engine.php";
            exit;
        }

    // Admin files requests calls
    } elseif (preg_match('#/files/.+\..+#', $request_url)) {
        if (is_file($root.'/app'.$request_url)) {
            require $root.'/app'.$request_url;
            exit;
        }
    }
}

// ADMIN FILES
if (preg_match('#^admin:///#', $request_url)) { // pattern(?!patternbis) is a "negative lookahead": matches 'pattern' NOT followed by 'patternbis'
    $file = preg_replace('#admin://#', '', $request_url);
    
    if (is_file($root."/app/admin".$file)) {
        require $root."/app/admin".$file;
        exit;
    }   
}

// API calls
if (preg_match('#^/api/.+#', $request_url)) {
    if (preg_match('#/(helloasso|paheko)/(.+)$#', $request_url, $matches)) {
        $slug = end(explode('/', $request_url));
        
        // API data files
        if (preg_match('#/get#', $request_url)) {
            $file = $root . '/app/api/'.$matches[1].'/data/' . $slug . '.json';
            
            if (is_file($file)) {
                require $file;
                exit;
            }
        
        // API actions
        } else {
            if (in_array($slug, $matches[1] === 'helloasso' ? ['edit', 'fetch']:['fetch', 'download'])) {
                $action = $slug;

                require $root . "/app/api/".$matches[1].'/'.$matches[1]."-engine.php";
                exit;
            }
        }
    }
}

// TEMPORARY FILES
if (preg_match('#^/tmp/#', $request_url)) {
    $file = end(explode('/', $request_url));
    require $root . "/.temp/$file";
    exit;
}

// ARTICLE PAGE GENERATOR
if (preg_match('#^/articles/#', $request_url)) {
    if (preg_match('#/.+$#', $request_url)) {
        $slug = urldecode(end(explode('/', $request_url)));
        $status = 'published';
        require $root . "/app/pages/article.php";
        exit;
    } else {
        http_response_code(403);
        echo "403: Forbidden";
    }
}

// GENERAL BACKEND ACTIONS
if (preg_match('#^/engine/#', $request_url)) {
    $slug = end(explode('/', $request_url));
    
    if (in_array($slug, ['news', 'contact', 'search'])) {
        require $root . "/app/pages/backend/$slug-engine.php";
        exit;
    }
}

// USER ACTIONS
if (preg_match('#^/user/#', $request_url)) {
    if (preg_match('#/engine/.+#', $request_url)) {
        $slug = end(explode('/', $request_url));
        if (in_array($slug, ['bookmarks'])) {
            require $root . "/app/pages/backend/$slug-engine.php";
            exit;
        }
    }

}

// SEARCH ACTIONS
if (preg_match('#^/search/#', $request_url)) {
    if (!preg_match('#/search/.+/;+#', $request_url)) {
        $query_slug = urldecode(end(explode('/', $request_url)));
        require $root . "/app/pages/search.php";
        exit;
    }
}

// CREDENTIAL ACTIONS
if (preg_match('#^/auth/#', $request_url)) {
    if (preg_match('#/loginCAS$#', $request_url)) {
        $method = 'CAS';
        require $root . '/app/auth/authentification.php';
        exit;
    
    } elseif (preg_match('#/auth/.+$#', $request_url)) {
        $slug = end(explode('/', $request_url));
        if (in_array($slug, ['login', 'logout', 'register', 'reset-credentials', 'update-credentials', 'check'])) {
            $action = $_POST['action'] ?? $_GET['action'] ?? $slug; //explode('-', $slug)[0];

            $_SESSION['reset-token'] = $url_parameters['token'] ?? null;

            require $root . "/app/auth/authentification.php";
            exit;
        }
    
    } elseif (preg_match('#/submit$#', $request_url)) {
        if (!isset($_POST['action'])) {
            echo "Unauthorized";
            exit;
        }
    
    } else {
        header('Location: /auth/login');
        exit;
    }    
}

// PAGES REQUEST
if (preg_match('#^/([a-zA-Z\-_0-9]+)$#', $request_url, $matches)) { // pattern(?!patternbis) is a "negative lookahead": matches 'pattern' NOT followed by 'patternbis'
    if (is_file($root . "/app/pages/".$matches[1].".php")) {
        require $root . "/app/pages/".$matches[1].".php";
        exit;
    }
}

// EXTENSION PRUNER
// At this point, the .php is not useful
if (in_array(end(explode('.', $request_url)), ['php', 'html'])) {
    $request_url = preg_replace('#\.(php|html)$#', '', $request_url);
    header("Location: $request_url");
    exit;
}

// classic redirections
switch ($request_url) {
    // home
	case '':
		require $serverroot . '/index.php';
        break;
	
	case '/':
		require $serverroot . '/index.php';
        break;
    
	case '/index':
        header("Location: /"); 
        break;
	
    // LOGIN
    case '/auth':
        header("Location: /auth/login"); 
        break;
    
    // PAGES - Mentions légales
    case '/legals':
        header("Location: /mentions-legales"); 
        break;
		
	// ADMIN - dashboard
    case '/admin':
        header("Location: /admin/dashboard"); 
        break;
	
    // ADMIN - dashboard
    case '/dashboard':
        header("Location: /admin/dashboard"); 
        break;
    
	// >> ADMIN - dashboard
    case '/admin/dashboard':
        require $root . '/app/admin/dashboard.php';
        break;
    
    // USER - dashboard
    case '/user':
        header("Location: /user/dashboard");
        break;
    
    // >> USER - dashboard
    case '/user/dashboard':
        require $root . '/app/user/dashboard.php';
        break;
    
    // USER - dashboard
    case '/user/favoris':
        require $root . '/app/user/bookmarks.php';
        break;
    
    // USER - bookmarks
    case '/user/bookmarks':
        require $root . '/app/user/bookmarks.php';
        break;
    
    // USER - account info
    case '/user/myaccount':
        require $root . '/app/user/account.php';
        break;
    
    default:
        http_response_code(404);
        echo "SERVER ERROR<br>404: URL '$request_url' not found on server<br>Retour au <a href='/'>menu</a>";
}

?>