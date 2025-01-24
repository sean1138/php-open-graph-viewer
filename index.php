<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Metadata Extractor</title>
	<style>
		:root{
			font-family: Arial, sans-serif;
			line-height: 1.5;
			font-size:100.01%;
			--bgc1: hsl(0, 4%, 96%);
			--bgc2: hsl(0, 1%, 92%);
		}
		html{
			height: 100%;
		}
		body {
			margin: 0;
			background: var(--bgc2);
			/* remove empty space below footer on short content pages 1/2 */
			display: flex;
			flex-direction: column;
			min-height: 100vh;
			height: 100%;
			/* allow animate to height:auto */
			interpolate-size: allow-keywords;
		}
		/*** START typography ***/
		/* this choice of font-family is supposed to render text the same across platforms
		 * letter-spacing makes the font a bit more legible
		 */
		body, input, button, textarea, select {
			font-family: "Lucida Sans Unicode","Lucida Grande","Lucida Sans",Verdana,Arial, sans-serif;
		}
		h1, h2, h3, h4, h5, h6 {
			font-family: Georgia, "DejaVu Serif", serif; letter-spacing: 1px;
		}
		pre, tt, code, kbd, samp, var {
			font-family: "Courier New", Courier, monospace;
		}
		/* avoid browser default inconsistent heading font-sizes - and pre/code/kbd too */
		H1, H2, H3, H4, H5, H6, PRE, CODE, KBD {
			font-size:1em;
		}
		H1, H2, H3, H4, H5, H6{
			margin: 0.25em 0;
			font-weight:700;
			line-height: 1;
		}
		/*ensure font-weight heritage for headings with links inside also on IE8*/
		H1 A, H2 A, H3 A,H4 A, H5 A, H6 A { font-weight: inherit;}
		H1 {font-size: 2.625em;}/*golden 16 main 42 title*/
		H2 {font-size: 1.625em;}/*golden 16 main 26 headline*/
		H3 {font-size: 1.25em;}/*golden 16 main sub-headline*/
		H4 {font-size: 1.2em;}
		H5 {font-size: 1.1em;}
		H6 {font-size: 1em;}
		p{
			margin: 0.5em 0;
		}
		/*** END typography ***/
		header, main, footer{
			display: flex;
			flex-direction: column;
			padding: 1rem;
		}
		header, footer{
			border: 1px solid rgba(0, 0, 0, 0.05);
		}
		main {
			/* remove empty space below footer on short content pages 2/2 */
			margin-bottom: auto;
			gap: 1rem;
		}
		footer{
			align-items: center;
		}
		form {
			display: flex;
			flex-wrap: wrap;
		}
		label, summary{
			cursor: pointer;
		}
		[for="url"]{
			width: 100%;
		}
		input[type="url"] {
			flex-grow: 1;
			padding: 0.5rem;
			font-size: 1rem;
		}
		button {
			margin-left: 1em;
			padding: 0.5rem 1rem;
			font-size: 1rem;
			cursor: pointer;
		}
		.metadata, .link-preview, .media-preview {
			padding: 1rem;
			border: 1px solid #ccc;
			border-radius: 5px;
			background-color: #f9f9f9;
		}
		.metadata, .media-preview{
			padding: 0;
		}
		summary{
			padding: 1rem;
		}
		.contents{
			padding: 1rem;
			padding-top: 0;
		}
		.media-preview img, .preview img {
			max-width: 100%;
			height: auto;
		}
		iframe, video {
			width: 100%;
			max-width: 600px;
			height: 338px; /* 16:9 aspect ratio */
			height: auto;
			aspect-ratio: 16/9;
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
		}
		.link-preview .details p {
			font-size: 0.9rem;
			color: #555;
		}
		/*	START animate details open/close	*/
		@keyframes details-show {
			from {
				opacity:0;
				transform: var(--details-translate, translateY(-0.5em));
			}
		}

		details[open] > *:not(summary) {
			animation: details-show 150ms ease-in-out;
		}
		/*	END animate details open/close	*/
	</style>
</head>
<body>
	<header>
		<h1>URL Metadata Viewer</h1>
		<p><a href="https://ogp.me/" target="_blank">What is OpenGraph</a>?</p>
		<form method="POST">
			<label for="url">Enter a URL to analyze:</label><br>
			<input type="url" id="url" name="url" placeholder="https://example.com" required>
			<button type="submit">Fetch Metadata</button>
		</form>
	</header>
	<main>
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
				echo '<summary>All the Metadata</summary>';
				echo '<div class="contents">';
				echo '<h2>Metadata for: <a href="' . htmlspecialchars($url) . '" target="_blank">'. htmlspecialchars($url) .'</a></h2>';
				if (!empty($metadata)) {
					foreach ($metadata as $key => $value) {
						echo '<p><strong>' . htmlspecialchars($key) . ':</strong> ' . htmlspecialchars($value) . '</p>';
					}
				} else {
					echo '<p>No metadata found!</p>';
				}
				echo '</div>';
				echo '</details>';
				echo '</div>';

				// Display a media preview
				if ($ogVideo || $twitterPlayer) {
					echo '<div class="media-preview">';
					echo '<details>';
					echo '<summary>Image/Video Embed</summary>';
					echo '<div class="contents">';
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
					echo '</div>';
					echo '</details>';
					echo '</div>';
				}

				// Build and display a link preview
				if ($ogTitle || $ogDescription || $ogImage) {
					echo '<div class="link-preview">';
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
	</main>
	<footer>
		<p>© 2025.01.22 - <span class="current-year">2025</span></p>
	</footer>
	<script>
		// get current year
		document.querySelector('.current-year').textContent = new Date().getFullYear();
	</script>
</body>
</html>
