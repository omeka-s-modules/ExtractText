# Extract Text

Extract text from files to make them searchable and machine readable.

Once installed and active, this module has the following features:

- The module adds an "extracted text" property where it sets extracted text to
  media and items.
- When adding a media, the module will automatically extract text from the file
  and set the text to the media.
- When adding or editing an item, the module will automatically aggregate the
  media text (in order) and set the text to the item.
- When batch updating items, the user can choose to refresh or clear the
  extracted text.

## Supported file formats:

- DOC (application/msword)
- DOCX (application/vnd.openxmlformats-officedocument.wordprocessingml.document)
- HTML (text/html)
- PDF (application/pdf)
- RTF (application/rtf)
- TXT (text/plain)

## Extractors:

### Catdoc

Used to extract text from DOC and RTF files. Requires [catdoc](https://linux.die.net/man/1/catdoc).

### Doc2txt

Used to extract text from DOCX files. Requires [doc2txt](http://docx2txt.sourceforge.net/).

### Filegetcontents

Used to extract text from TXT files. No requirements.

### Lynx

Used to extract text from HTML files. Requires [lynx](https://linux.die.net/man/1/lynx).

### Pdftotext

Used to extract text from PDF files. Requires [pdftotext](https://linux.die.net/man/1/pdftotext).
