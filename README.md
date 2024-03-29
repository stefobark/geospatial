geospatial
==========

###Example MySQL/Sphinx Geospatial Search###
Quick example of MySQL's CONTAINS() and ST_CONTAINS() functions -- and Sphinx's CONTAINS(GEOPOLY2D()) and GEODIST() functions.

Connecting to MySQL on 127.0.0.1 3306 with 'root' user and no password.. on a database named 'gro' (which has been populated with data using [ogr2ogr](http://www.gdal.org/1.11/ogr2ogr.html)). Also, Sphinx is at 127.0.0.1 9306, and has a realtime index filled with data from [geonames.org](http://geonames.org). I'm using [this example](https://github.com/adriannuta/SphinxGeoExample) provided by Adrian Nuta (lead support engineer at Sphinx). Take a look.

Also, I wrote more about what's going on here in [this blog post](http://sphinxsearch.com/blog/2014/09/05/a-geospatial-search-odysse/). Check it out.
