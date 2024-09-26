# Extract Text

Extract text from files to make them searchable and machine readable.

This module has the following features:

- The module adds an "extracted text" property where it sets extracted text to media and items.
- When adding a media, the module will automatically extract text from the file and set the text to the media.
- When adding or editing an item, the module will automatically aggregate the media text (in order) and set the text to the item.
- When editing an item or batch editing items, the user can choose to refresh or clear the extracted text.
- When editing a media, the user can choose to refresh or clear the extracted text.
- On the module configuration page, the user can:
  - See which extractors are available on their system.
  - Disable individual extractors.
  - Set individual extractors to only run in the background.

## Extractors:

### catdoc

Used to extract text from DOC and RTF files. Requires [catdoc](https://linux.die.net/man/1/catdoc).

### docx2txt

Used to extract text from DOCX files. Requires [docx2txt](http://docx2txt.sourceforge.net/).

### domdocument

Used to extract text from XML files. No requirements.

### filegetcontents

Used to extract text from TXT files. No requirements.

### lynx

Used to extract text from HTML files. Requires [lynx](https://linux.die.net/man/1/lynx).

### odt2txt

Used to extract text from ODT files. Requires [odt2txt](https://linux.die.net/man/1/odt2txt).

### pdftotext

Used to extract text from PDF files. Requires [pdftotext](https://linux.die.net/man/1/pdftotext), a part of the `poppler-utils` package.

### tesseract

Used to extract text from image files (OCR). Requires [tesseract](https://tesseract-ocr.github.io/tessdoc/Command-Line-Usage.html).

## Supported file formats:

- DOC (application/msword)
- DOCX (application/vnd.openxmlformats-officedocument.wordprocessingml.document)
- HTML (text/html)
- ODT (application/vnd.oasis.opendocument.text)
- PDF (application/pdf)
- RTF (application/rtf)
- TXT (text/plain)
- XML (application/xml)

Images files are supported by tesseract if compiled with the [required libraries](https://tesseract-ocr.github.io/tessdoc/InputFormats.html):

- BMP (image/bmp)
- GIF (image/gif)
- JP2 (image/jp2)
- JPG (image/jpeg)
- PNG (image/png)
- TIFF (image/tiff)
- WEBP (image/webp)

Note that some file extensions or media types may be disallowed in your global settings.

# Copyright

ExtractText is Copyright © 2019-present Corporation for Digital Scholarship, Vienna, Virginia, USA http://digitalscholar.org

The Corporation for Digital Scholarship distributes the Omeka source code
under the GNU General Public License, version 3 (GPLv3). The full text
of this license is given in the license file.

The Omeka name is a registered trademark of the Corporation for Digital Scholarship.

Third-party copyright in this distribution is noted where applicable.

All rights not expressly granted are reserved.
