<?xml version="1.0" encoding="UTF-8"?>
<!--
    Extract text from xml alto, line by line.

    @copyright Daniel Berthereau, 2022 for Numistral (Université de Strasbourg).
    @license CeCILL 2.1 https://cecill.info/licences/Licence_CeCILL_V2.1-fr.txt
-->

<xsl:stylesheet version="1.0"
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
    xmlns:alto2="http://www.loc.gov/standards/alto/ns-v2#"
    xmlns:alto3="http://www.loc.gov/standards/alto/ns-v3#"
    xmlns:alto4="http://www.loc.gov/standards/alto/ns-v4#"
    >

    <xsl:output method="text" encoding="UTF-8" />

    <!-- End of line (the Linux one, because it's simpler and smarter). -->
    <xsl:param name="end_of_line"><xsl:text>&#x0A;</xsl:text></xsl:param>

    <xsl:template match="/">
        <xsl:apply-templates select="//alto2:TextBlock | //alto3:TextBlock | //alto4:TextBlock"/>
        <xsl:value-of select="$end_of_line"/>
    </xsl:template>

    <xsl:template match="alto2:TextBlock | alto3:TextBlock | alto4:TextBlock">
        <xsl:apply-templates select="alto2:TextLine | alto3:TextLine | alto4:TextLine"/>
        <xsl:value-of select="$end_of_line"/>
    </xsl:template>

    <xsl:template match="alto2:TextLine | alto3:TextLine | alto4:TextLine">
        <xsl:apply-templates select="alto2:String | alto3:String | alto4:String"/>
        <xsl:value-of select="$end_of_line"/>
    </xsl:template>

    <xsl:template match="alto2:String | alto3:String | alto4:String">
        <xsl:value-of select="@CONTENT"/>
        <xsl:if test="position() != last()">
            <xsl:text> </xsl:text>
        </xsl:if>
    </xsl:template>

</xsl:stylesheet>
