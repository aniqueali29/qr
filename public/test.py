
from reportlab.lib.pagesizes import A4
from reportlab.platypus import SimpleDocTemplate, Table, TableStyle, Paragraph
from reportlab.lib import colors
from reportlab.lib.styles import ParagraphStyle

# File path
file_path_onepage = "/mnt/data/Electrical_Engineering_Books_List_OnePage.pdf"

# Create document with minimal margins for compactness
doc = SimpleDocTemplate(file_path_onepage, pagesize=A4, leftMargin=10, rightMargin=10, topMargin=10, bottomMargin=10)

# Compact text style
tiny_style = ParagraphStyle(
    name='Tiny',
    fontSize=6,
    leading=7,
    spaceAfter=0,
    spaceBefore=0
)

# Header style
header_style = ParagraphStyle(
    name='Header',
    fontSize=8,
    leading=9,
    spaceAfter=1,
    spaceBefore=1,
    textColor=colors.white
)

# Books list
books = [

]

# Table data
data = [[Paragraph("<b>No.</b>", header_style), Paragraph("<b>Book Title</b>", header_style), Paragraph("<b>Qty</b>", header_style)]]
for i, book in enumerate(books, start=1):
    data.append([str(i), Paragraph(book, tiny_style), "1"])

# Create table and style
table = Table(data, colWidths=[18, 460, 18])
table.setStyle(TableStyle([
    ('BACKGROUND', (0, 0), (-1, 0), colors.HexColor("#003366")),
    ('ALIGN', (0, 0), (0, -1), 'CENTER'),
    ('VALIGN', (0, 0), (-1, -1), 'TOP'),
    ('GRID', (0, 0), (-1, -1), 0.25, colors.grey),
    ('LEFTPADDING', (0, 0), (-1, -1), 2),
    ('RIGHTPADDING', (0, 0), (-1, -1), 2),
    ('TOPPADDING', (0, 0), (-1, -1), 1),
    ('BOTTOMPADDING', (0, 0), (-1, -1), 1),
]))

# Build document
doc.build([table])

file_path_onepage
