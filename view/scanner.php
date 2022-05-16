<?php
 
	require_once('top.php');

?>

</html>
	<head>
		<link type="text/css"          href="css/bootstrap.min.css" media="all" rel="stylesheet" />
		<link type="text/css"          href="css/bootstrap-customization.css" media="all" rel="stylesheet" />
		<link type="text/css"          href="css/jquery-ui.min.css" media="all" rel="stylesheet" />
		<link type="text/css"          href="css/common.css" media="all" rel="stylesheet" />
		<script type="text/javascript" src='js/jquery-3.2.1.min.js'></script>
		<script type="text/javascript" src='js/jquery-ui.min.js'></script>
		<script type="text/javascript" src='js/spbc-scanner-plugin.js'></script>
		<script type="text/javascript" src='js/common.js'></script>
		<?php // require_once 'font.php'; ?>
	</head>
	<body>
		<div class="wrapper wrapper-main">
			
			<?php require_once('header.php'); ?>
			
			<form method="POST" action="index.php">
				<textarea name="text_to_check"
					style="width: 100%; height: 300px;"
				><?=isset($_POST['text_to_check']) ? $_POST['text_to_check'] : ''?></textarea>
				<input type="submit" name="submit1" value="GO">
			</form>
			
			<?php
            
                    $file = new \CleantalkSP\SpbctWP\Scanner\Heuristic\Controller(
                        ! EMPTY( $_POST['text_to_check'] )
                            ? array('content' => $_POST['text_to_check'])
                            : array('path'    => ROOT . '\\to_research\\test.php')
                    );
					
					if(empty($file->error)){
						echo 'process file - OK!</br>';
						$file->processContent();
					}else{
                        var_dump($file->error);
					}
					echo 'VERDICT:</br>';
					var_dump($file->verdict);
					//echo 'FILE:</br>';
					//var_dump($file->gather_lexems());
					echo 'INCLUDES:</br>';
					//var_dump($file->getIncludes());
					echo 'SQL REQUESTS:</br>';
					//var_dump($file->sql_requests);
					echo 'VARIABLES BAD:</br>';
					//var_dump($file->variables_bad);
					//var_dump( $file->gather_lexems());

                    //echo '<p>' . htmlentities( $file->gather_lexems() ) . '</p>';
					$output = $file;
				//*/
				
				/* Test shit
					
					if(isset($_POST['par1'])){
						
						$var = $_POST['par1'];
						
						$options = array('flags' => '');
						if(isset($_POST['check1'])) $options['flags'] = FILTER_FLAG_SCHEME_REQUIRED;
						if(isset($_POST['check2'])) $options['flags'] = FILTER_FLAG_HOST_REQUIRED;
						if(isset($_POST['check3'])) $options['flags'] = FILTER_FLAG_PATH_REQUIRED;
						if(isset($_POST['check4'])) $options['flags'] = FILTER_FLAG_QUERY_REQUIRED;
						
						$var = filter_var($var, FILTER_VALIDATE_URL, FILTER_FLAG_SCHEME_REQUIRED or FILTER_FLAG_HOST_REQUIRED);
						var_dump($var);
						var_dump(FILTER_FLAG_QUERY_REQUIRED);
					}
					
				//*/
			?>
		</div>
		<div class="log">
			<?php require_once 'log.php'; ?>
		</div>
		<script type="text/javascript" src='js/bootstrap.min.js'></script>
	</body>
</html>
