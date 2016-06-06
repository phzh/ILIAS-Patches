## ILIAS-Patches
All patches have a comment that starts with `// PHZH: [...]`


**Login-Patch für AD/Evento-Fallback**  
**MMP-Cookie setzen**

- Services\Init
  - classes\class.ilInitialisation.php


**Ordner in Kategorien ermöglichen**

- Modules\Folder
  - module.xml: Linie hinzufügen:
        ```xml
        <parent id="cat">cat</parent>
        ``` 
  

**E-Mail-Adresse in Umfragen ausgeben**

- Modules\Survey
  - classes\class.ilObjSurvey.php
  - classes\class.ilSurveyEvaluationGUI.php
  

**Excel-Export von Einzelfragen in Tests**

- Modules\Test
  - classes\class.ilQuestionExport.php
  - classes\class.ilTestEvaluationGUI.php
  - classes\tables\class.ilResultsByQuestionTableGUI

  
**Einbinden von MMP-Dateien in Content**  
**Min. Breite von Audio-Dateien**  
**Vorschauen in Videos**

- Services\COPage
  - xsl\page.xsl