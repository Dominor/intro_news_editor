<!DOCTYPE html>
<html>
	<head>
	
		<meta charset="utf-8">
		
		<title>
			Edição de Notícia
		</title>
		
	</head>
	<body>
		<?php
		require_once('include/mysqliconnect.php');
		$create_news_flag = true;
		
			if(isset($_POST['save_news'])) {	
				$article_id = $_POST['articleId'];
				
				if($article_id == -1) {  // Insert a new article
				
					$title = $_POST['news_name'];
					$summary = $_POST['news_summary'];
					$date = $_POST['news_date'];
					$body = $_POST['news_body'];
					
					$query = "INSERT INTO article (id, title, date, summary, body, figure) VALUES (NULL,?,?,?,?,?);";
					
					$stmt = mysqli_prepare($dbc, $query);
					
					$null = NULL; //needed to pass NULL by reference to bind_param (sheesh)
					mysqli_stmt_bind_param($stmt, 'ssssb', $title, $date, $summary, $body, $null);
										
					if ($_FILES['news_picture']['size'] != 0) {
						$file_to_String = file_get_contents($_FILES['news_picture']['tmp_name']);
						mysqli_stmt_send_long_data($stmt, 0, $file_to_String);
					}
					/* Sadly file uploading doesn't work for some unknown reason. File gets loaded into server, located in temp path
					but the mysqli_stmt_send_long_data just fails despite its arguments being correct.*/
					
					mysqli_stmt_execute($stmt);
					
					mysqli_stmt_close($stmt);
					
					// Insertion of new article done
					
					/* Get selected categories from the categories select box (1 or more) and create a new entry 
					in the relationship table for each category related to the current article */
					$article_categories = $_POST['news_category'];
					$comma_sep_categories = implode(',', $article_categories);
					
					echo $comma_sep_categories;
					
					$query = "SELECT id FROM category WHERE name IN ('" . $comma_sep_categories ."');";
					$result = mysqli_query($dbc,$query);
					
					var_dump($result);
					
					mysqli_data_seek($result, 0);
					$cat_ids = array();     // empty array
					while($row = mysqli_fetch_assoc($result)) {
						$cat_ids[] = $row['id'];
					}
					
					var_dump($cat_ids);
					/* free result set */
					mysqli_free_result($result);
					
					// Do one or multiple inserts of all the categories ids of the current article
					$query = "INSERT INTO article_has_category (id_article, id_category) VALUES (?,?);";
					$stmt = mysqli_prepare($dbc, $query);
					for($i = 0; $i < count($cat_ids); $i++) {
						mysqli_stmt_bind_param($stmt, 'ii', intval($article_id), intval($cat_ids[$i]));
						var_dump($stmt, $cat_ids);
						mysqli_stmt_execute($stmt);	
					}
					mysqli_stmt_close($stmt);
					
				} else {
					
					// Update article records
					
					//Get handle to uploaded image file
					$img = fopen($_FILES['news_picture']['tmp_filename'], rb);
				
					$title = $_POST['news_name'];
					$summary = (isset($POST['news_summary'])) ? $_POST['news_summary'] : NULL;
					$date = $_POST['news_date'];
					$body = (isset($POST['news_body'])) ? $_POST['news_body'] : NULL;
					
					$query = "UPDATE article SET title=?, date=?, summary=?, body=?, figure=? WHERE article.id=?;";
					
					$stmt = mysqli_prepare($dbc, $query);
					
					mysqli_stmt_bind_param($stmt, "ssssb", $title, $date, $summary, $body, $img);
					
					mysqli_stmt_execute($stmt);
					mysqli_stmt_close($stmt);
					
					/* Get selected categories from the categories select box (1 or more) and create a new entry 
					in the relationship table for each category related to the current article */
					$chosen_categories = $_POST['news_category'];
					$article_id = $_POST['articleId'];
					$query = "SELECT category.id, category.name FROM article INNER JOIN article_has_category 
					ON article.id=article_has_category.id_article INNER JOIN category ON article_has_category.id_category=category.id
					WHERE article.id=" . $article_id . ";";
					$result = mysqli_query($dbc,$query);
					$cat_ids = array();     // empty array
					$registered_categories = mysqli_fetch_all($result, MYSQLI_BOTH);
					$wonderland = array_flip($registered_categories); // IDs become the values and category names become the keys
					/* free result set */
					mysqli_free_result($result);
					
					for($i = 0; $i < count($chosen_categories); $i++) {
						if(!(isset($wonderland[$chosen_categories[$i]]))) {
						
							$query = "SELECT id FROM category WHERE name='" . $chosen_categories[$i] . "';";
							$result = mysqli_query($dbc,$query);
							$cat = mysqli_fetch_assoc($result);
							$cat_id = $cat['id'];
							/* free result set */
							mysqli_free_result($result);
							
							$query = "INSERT INTO article_has_category (id_article, id_category) VALUES (?,?);";
							$stmt = mysqli_prepare($dbc, $query);
							
							mysqli_stmt_bind_param($stmt, 'ii', $article_id, $cat_id);
							mysqli_stmt_execute($stmt);
							mysqli_stmt_close($stmt);
							
						} else {
							// delete $chosen_categories[i] from wonderland array
							unset($wonderland[$chosen_categories[$i]]);
						}
					}
					if ((count($wonderland) != 0)) {
						
						// There are registered categories remaining, so they must be dessociated from the current article
						$query = "DELETE FROM article_has_category WHERE id_category=?";
						$stmt = mysqli_prepare($dbc, $query);
						foreach($wonderland as $key => $value) {
							mysqli_stmt_bind_param($stmt, "i", $wonderland[$key]);
					
							mysqli_stmt_execute($stmt);
						}
						mysqli_stmt_close($stmt);
					}
					
					fclose($img);
					mysqli_close($dbc);
				}
			} else if (isset($_POST['delete_news']) == 'Eliminar') {
				
				$article_id = $_POST['articleId'];
				$query = "DELETE FROM article WHERE id=" . $article_id . ";";
				$result = mysqli_query($dbc,$query);
				/* free result set */
				mysqli_free_result($result);
				
				/* It's only necessary to delete the article record from the article table 
				since the deletion spreads into the relationship table via foreign key restraints */
			}
		?>
	</body>
</html>