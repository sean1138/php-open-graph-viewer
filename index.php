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
		.metadata, .preview {
			margin-top: 1rem;
			padding: 1rem;
			border: 1px solid #ccc;
			border-radius: 5px;
			background-color: #f9f9f9;
		}
		.metadata h2, .preview h2 {
			margin-top: 0;
		}
		.metadata p, .preview p {
			margin: 0.5rem 0;
		}
		.preview img {
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

				$ogImage = $ogVideo = $twitterPlayer = null;

				foreach ($metaTags as $tag) {
					$property = $tag->getAttribute('property') ?: $tag->getAttribute('name');
					$content = $tag->getAttribute('content');
					if ($property && $content) {
						$metadata[$property] = $content;

						// Capture specific media previews
						if ($property === 'og:image') {
							$ogImage = $content;
						}
						if ($property === 'og:video') {
							$ogVideo = $content;
						}
						if ($property === 'twitter:player') {
							$twitterPlayer = $content;
						}
					}
				}

				// Display the metadata
				echo '<div class="metadata">';
				echo '<h2>Metadata for: <a href="' . htmlspecialchars($url) . '" target="_blank">'. htmlspecialchars($url) .'</a></h2>';
				if (!empty($metadata)) {
					foreach ($metadata as $key => $value) {
						echo '<p><strong>' . htmlspecialchars($key) . ':</strong> ' . htmlspecialchars($value) . '</p>';
					}
				} else {
					echo '<p>No metadata found!</p>';
				}
				echo '</div>';

				// Display media preview
				echo '<div class="preview">';
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
					echo '<iframe src="' . htmlspecialchars($twitterPlayer) . '" allowfullscreen></iframe>';
				}

				if (!$ogImage && !$ogVideo && !$twitterPlayer) {
					echo '<p>No media preview available for this URL.</p>';
				}

				echo '</div>';
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
