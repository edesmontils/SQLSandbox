class LibXML :
    __debug : bool
    
    def __init__(self, deb : bool):
        self.__debug = deb
        #TODO
        #libxml_use_internal_errors(True)
    
    def _display_xml_error(self, error):
        result = ""
        if (error.level == LIBXML_ERR_WARNING):
            result = "Warning " + error.code+": "
        elif (error.level == LIBXML_ERR_ERROR):
            result = "Error "+error.code+": "
        elif (error.level == LIBXML_ERR_FATAL):
            result = "Fatal Error " + error.code+": "
        else:
            result = "Unknown Error "+error.code+": "
        
        result += error.message.strip() + "\n<br/> Line: "+ error.line +"\n<br> Column: "+error.column
        if (error.file != None):
            result += "\n<br/>  File: "+error.file
        return result+"\n<br/>"

    def afficheErreur(self):
        Tab_erreur = None #TODO libxml_get_errors()
        rep = ""
        if self.__debug != None:
            rep = "Affichage des erreurs :\n<br/>"
        for error in Tab_erreur:
            if self.__debug != None:
                rep += self._display_xml_error(error)
        if self.__debug!= None:
            rep += "Fin de l'affichage des erreurs :\n<br/> "
        #TODO libxml_clear_errors()
        return rep
    
    def afficheDerniereErreur(self):
        error = None #TODO libxml_get_last_error()
        rep = "" #TODO display_xml_error(error)
        return rep