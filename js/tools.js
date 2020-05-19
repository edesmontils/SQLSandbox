//<script language="javascript">


//==================================================
// Pris sur http://snippets.dzone.com/tag/javascript 
//==================================================

// Check for valid email address
function is_valid_email($email)
{
	if(preg_match("/[a-zA-Z0-9_-.+]+@[a-zA-Z0-9-]+.[a-zA-Z]+/", $email) > 0)
		return true;
	else
		return false;
}

// Month Day Year Smart Dropdowns
function mdy($mid = "month", $did = "day", $yid = "year", $mval, $dval, $yval)
	{
		if(empty($mval)) $mval = date("m");
		if(empty($dval)) $dval = date("d");
		if(empty($yval)) $yval = date("Y");
		
		$months = array(1 => "January", 2 => "February", 3 => "March", 4 => "April", 5 => "May", 6 => "June", 7 => "July", 8 => "August", 9 => "September", 10 => "October", 11 => "November", 12 => "December");
		$out = "<select name='$mid' id='$mid'>";
		foreach($months as $val => $text)
			if($val == $mval) $out .= "<option value='$val' selected>$text</option>";
			else $out .= "<option value='$val'>$text</option>";
		$out .= "</select> ";

		$out .= "<select name='$did' id='$did'>";
		for($i = 1; $i <= 31; $i++)
			if($i == $dval) $out .= "<option value='$i' selected>$i</option>";
			else $out .= "<option value='$i'>$i</option>";
		$out .= "</select> ";

		$out .= "<select name='$yid' id='$yid'>";
		for($i = date("Y"); $i <= date("Y") + 2; $i++)
			if($i == $yval) $out.= "<option value='$i' selected>$i</option>";
			else $out.= "<option value='$i'>$i</option>";
		$out .= "</select>";
		
		return $out;
	}
	
// this snippet is useful for making urls slugs, or comparing user inputs to a normalized string.
var normalize = (function() {
        var from = "ÃÀÁÄÂÈÉËÊÌÍÏÎÒÓÖÔÙÚÜÛãàáäâèéëêìíïîòóöôùúüûÑñÇç", 
            to   = "AAAAAEEEEIIIIOOOOUUUUaaaaaeeeeiiiioooouuuunncc",
            mapping = {};
        
        for(var i=0; i<from.length; i++)
            mapping[from.charAt(i)] = to.charAt(i);
        
        return function(str) {
            var ret = []
            for(var i=0; i<str.length; i++) {
                var c = str.charAt(i)
                if(mapping.hasOwnProperty(str.charAt(i)))
                    ret.push(mapping[c]);
                else
                    ret.push(c);
            }
            return ret.join('').replace(/[^-A-Za-z0-9]+/g, '-').toLowerCase();
        }

    })();
    
//Javascript String Trimming Prototypes    
String.prototype.trim = function() {
 return this.replace(/^\s+|\s+$/g,"");
}

String.prototype.ltrim = function() {
 return this.replace(/^\s+/,"");
}
String.prototype.rtrim = function() {
 return this.replace(/\s+$/,"");
}

//based on: http://en.wikibooks.org/wiki/Algorithm_implementation/Strings/Levenshtein_distance
//and:  http://en.wikipedia.org/wiki/Damerau%E2%80%93Levenshtein_distance
function levenshtein( a, b )
{
	var i;
	var j;
	var cost;
	var d = new Array();
 
	if ( a.length == 0 )
	{
		return b.length;
	}
 
	if ( b.length == 0 )
	{
		return a.length;
	}
 
	for ( i = 0; i <= a.length; i++ )
	{
		d[ i ] = new Array();
		d[ i ][ 0 ] = i;
	}
 
	for ( j = 0; j <= b.length; j++ )
	{
		d[ 0 ][ j ] = j;
	}
 
	for ( i = 1; i <= a.length; i++ )
	{
		for ( j = 1; j <= b.length; j++ )
		{
			if ( a.charAt( i - 1 ) == b.charAt( j - 1 ) )
			{
				cost = 0;
			}
			else
			{
				cost = 1;
			}
 
			d[ i ][ j ] = Math.min( d[ i - 1 ][ j ] + 1, d[ i ][ j - 1 ] + 1, d[ i - 1 ][ j - 1 ] + cost );
			
			if(
         i > 1 && 
         j > 1 &&  
         a.charAt(i - 1) == b.charAt(j-2) && 
         a.charAt(i-2) == b.charAt(j-1)
         ){
          d[i][j] = Math.min(
            d[i][j],
            d[i - 2][j - 2] + cost
          )
         
			}
		}
	}
 
	return d[ a.length ][ b.length ];
}





//</script> 