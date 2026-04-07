<?xml version="1.0" encoding="UTF-8"?>
<?xml-stylesheet href="<?php echo $this->xsl ?>" type="text/xsl"?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
 <channel>
  <title><?php echo $this->title ?></title>
  <pubDate><?php echo $this->pubDate ?></pubDate>
  <link><?php echo $this->url ?></link>
  <atom:link rel="self" type="application/rss+xml" title="<?php echo $this->title ?>" href="<?php echo $this->rss_url ?>" />
  <description><?php echo $this->description ?></description>

<?php if (!empty($this->items)): ?>
<?php foreach ($this->items as $item): ?>
  <item>
   <title><?php echo $item['title'] ?></title>
   <description><?php echo $item['description'] ?></description>
   <pubDate><?php echo $item['pubDate'] ?></pubDate>
   <link><?php echo $item['url'] ?></link>
  </item>
<?php endforeach ?>
<?php endif ?>

 </channel>
</rss>
