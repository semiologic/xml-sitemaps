<?xml version="1.0" encoding="UTF-8" ?>
<!--
	Copyright (c) 2009-2015 Semiologic <https://www.semiologic.com>
	GNU/GPL licensed
-->
<xsl:stylesheet version="1.0"
	xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
	xmlns:sitemap="http://www.sitemaps.org/schemas/sitemap/0.9"
	>
<xsl:output method="html" version="1.0" encoding="UTF-8" indent="yes"/>
<xsl:template match="/">
	<xsl:variable name="lower" select="'abcdefghijklmnopqrstuvwxyz'"/>
	<xsl:variable name="upper" select="'ABCDEFGHIJKLMNOPQRSTUVWXYZ'"/>
<html>
	<head>
		<title>sitemap.xml</title>
	</head>
	<body>
		<h2>sitemap.xml</h2>
		<table width="100%" border="0" cellpadding="2" cellspacing="0">
			<tr align="center">
				<th align="left">URL</th>
				<th>Weight</th>
				<th>Freq</th>
				<th>Lastmod</th>
			</tr>
			<xsl:for-each select="sitemap:urlset/sitemap:url">
				<tr align="center">
					<td align="left">
						<a href="{sitemap:loc}"><xsl:value-of select="sitemap:loc" /></a>
					</td>
					<td>
						<xsl:value-of select="sitemap:priority" />
					</td>
					<td>
						<xsl:value-of select="concat(translate(substring(sitemap:changefreq, 1, 1),$lower, $upper),substring(sitemap:changefreq, 2))" />
					</td>
					<td>
						<xsl:value-of select="sitemap:lastmod" />
					</td>
				</tr>
			</xsl:for-each>
		</table>
		<p>Generator: <a href="https://www.semiologic.com/software/xml-sitemaps/">XML Sitemaps plugin</a>.</p>
	</body>
</html>
</xsl:template>

</xsl:stylesheet>