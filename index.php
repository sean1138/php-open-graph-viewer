<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Metadata Extractor</title>
	<style>
		body {
			font-family: Arial, sans-serif;
			margin: 2rem;
		}
		form {
			margin-bottom: 1rem;
		}
		input[type="url"] {
			width: 80%;
			padding: 0.5rem;
			font-size: 1rem;
		}
		button {
			padding: 0.5rem 1rem;
			font-size: 1rem;
			cursor: pointer;
		}
		.metadata, .preview, .media-preview {
			margin-top: 1rem;
			padding: 1rem;
			border: 1px solid #ccc;
			border-radius: 5px;
			background-color: #f9f9f9;
		}
		.metadata h2, .preview h2, .media-preview h2 {
			margin-top: 0;
		}
		.metadata p, .preview p, .media-preview p {
			margin: 0.5rem 0;
		}
		.media-preview img, .preview img {
			max-width: 100%;
			height: auto;
			margin-top: 1rem;
		}
		iframe, video {
			width: 100%;
			max-width: 600px;
			height: 338px; /* 16:9 aspect ratio */
			margin-top: 1rem;
			border: none;
		}
		.link-preview {
			display: flex;
			gap: 1rem;
			align-items: flex-start;
			border: 1px solid #ddd;
			padding: 1rem;
			border-radius: 5px;
			background-color: #fff;
		}
		.link-preview img {
			width: 120px;
			height: auto;
			border-radius: 5px;
			object-fit: cover;
		}
		.link-preview .details {
			flex: 1;
		}
		.link-preview .details h2 {
			font-size: 1.2rem;
			margin: 0;
		}
		.link-preview .details p {
			font-size: 0.9rem;
			color: #555;
			margin: 0.5rem 0;
		}
	</style>
</head>
<body>
	<h1>URL Metadata Viewer</h1>
	<p><a href="https://ogp.me/" target="_blank">What is OpenGraph</a>?</p>
	<form method="POST">
		<label for="url">Enter a URL to analyze:</label><br>
		<input type="url" id="url" name="url" placeholder="https://example.com" required>
		<button type="submit">Fetch Metadata</button>
	</form>

	<?php
	if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['url'])) {
		$url = filter_var($_POST['url'], FILTER_VALIDATE_URL);
		if ($url) {
			// Fetch the URL content
			$context = stream_context_create(['http' => ['ignore_errors' => true]]);
			$html = @file_get_contents($url, false, $context);

			if ($html !== false) {
				// Check the headers for encoding info
				$headers = $http_response_header ?? [];
				$charset = 'UTF-8'; // Default to UTF-8
				foreach ($headers as $header) {
					if (stripos($header, 'Content-Type:') !== false && preg_match('/charset=([\w-]+)/i', $header, $matches)) {
						$charset = $matches[1];
						break;
					}
				}

				// Convert content to UTF-8 if necessary
				$html = mb_convert_encoding($html, 'UTF-8', $charset);

				// Ensure DOMDocument uses UTF-8
				libxml_use_internal_errors(true);
				$doc = new DOMDocument('1.0', 'UTF-8');
				@$doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

				// Extract metadata
				$metadata = [];
				$metaTags = $doc->getElementsByTagName('meta');

				$ogImage = $ogVideo = $twitterPlayer = $ogTitle = $ogDescription = null;

				foreach ($metaTags as $tag) {
					$property = $tag->getAttribute('property') ?: $tag->getAttribute('name');
					$content = $tag->getAttribute('content');
					if ($property && $content) {
						$metadata[$property] = $content;

						// Capture specific OpenGraph and Twitter card data
						if ($property === 'og:image') {
							$ogImage = $content;
						}
						if ($property === 'og:video') {
							$ogVideo = $content;
						}
						if ($property === 'twitter:player') {
							$twitterPlayer = $content;
						}
						if ($property === 'og:title') {
							$ogTitle = $content;
						}
						if ($property === 'og:description') {
							$ogDescription = $content;
						}
					}
				}

				// Display the metadata
				echo '<div class="metadata">';
				echo '<details>';
				echo '<summary>All the Matadata</summary>';
				echo '<h2>Metadata for: <a href="' . htmlspecialchars($url) . '" target="_blank">'. htmlspecialchars($url) .'</a></h2>';
				if (!empty($metadata)) {
					foreach ($metadata as $key => $value) {
						echo '<p><strong>' . htmlspecialchars($key) . ':</strong> ' . htmlspecialchars($value) . '</p>';
					}
				} else {
					echo '<p>No metadata found!</p>';
				}
				echo '</details>';
				echo '</div>';

				// Display a media preview
				if ($ogVideo || $twitterPlayer) {
					echo '<div class="media-preview">';
					echo '<details>';
					echo '<summary>Image/Video</summary>';
					echo '<h2>Media Preview</h2>';
					if ($ogImage) {
						echo '<p><strong>Image:</strong></p>';
						echo '<img src="' . htmlspecialchars($ogImage) . '" alt="Open Graph Image Preview">';
					}
					if ($ogVideo) {
						echo '<p><strong>Video:</strong></p>';
						echo '<video controls src="' . htmlspecialchars($ogVideo) . '"></video>';
					} elseif ($twitterPlayer) {
						echo '<p><strong>Twitter Player:</strong></p>';
						echo '<iframe src="' . htmlspecialchars($twitterPlayer) . '"></iframe>';
					}
					echo '</details>';
					echo '</div>';
				}

				// Build and display a link preview
				if ($ogTitle || $ogDescription || $ogImage) {
					echo '<div class="preview link-preview">';
					if ($ogImage) {
						echo '<img src="' . htmlspecialchars($ogImage) . '" alt="Preview Image">';
					}
					echo '<div class="details">';
					if ($ogTitle) {
						echo '<h2>' . htmlspecialchars($ogTitle) . '</h2>';
					}
					if ($ogDescription) {
						echo '<p>' . htmlspecialchars($ogDescription) . '</p>';
					}
					echo '<p><a href="' . htmlspecialchars($url) . '" target="_blank">Visit Link</a></p>';
					echo '</div>';
					echo '</div>';
				}
			} else {
				echo '<p style="color: red;">Unable to fetch the URL. Please check the URL and try again.</p>';
			}
		} else {
			echo '<p style="color: red;">Invalid URL format. Please try again.</p>';
		}
	}
	?>
</body>
</html>
