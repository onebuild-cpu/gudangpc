# Complete List of Semantic HTML Tags

Semantic HTML tags define the meaning and structure of web content rather than just its visual appearance. Using these tags helps search engines, AI crawlers, and screen readers accurately parse and understand your pages.

---

## 1. Page Layout & Sectioning
* **`<header>`**: Defines introductory content or a group of navigation links for a page or section.
* **`<nav>`**: Marks a block of major navigation links, such as a main menu or table of contents.
* **`<main>`**: Wraps the dominant, unique content of the page (use only one per page).
* **`<article>`**: Identifies self-contained content that makes sense on its own (e.g., a blog post, forum comment, or product card).
* **`<section>`**: Groups related content thematically, typically paired with a heading.
* **`<aside>`**: Marks content indirectly related to the main text, such as sidebars, callout boxes, or ad blocks.
* **`<footer>`**: Defines the closing area for a page or section, usually holding copyright info, author details, or secondary links.
* **`<search>`**: Wraps a section containing search form controls and filtering tools.
* **`<address>`**: Provides contact information for the nearest `<article>` or the entire `<body>`.

## 2. Headings & Text Grouping
* **`<h1>` to `<h6>`**: Establishes a strict six-level outline hierarchy for page titles and subheadings.
* **`<hgroup>`**: Groups a primary heading with secondary subheadings or taglines.
* **`<p>`**: Defines a standard paragraph of text.
* **`<blockquote>`**: Marks a long quotation from an external source, often paired with a `cite` attribute.
* **`<pre>`**: Preserves exact whitespace and line breaks, commonly used for code blocks.
* **`<hr>`**: Represents a thematic break or topic shift between paragraphs.

## 3. Lists & Data Tables
* **`<ul>`**: Creates an unordered bulleted list where item sequence does not matter.
* **`<ol>`**: Creates an ordered numbered list for step-by-step instructions or rankings.
* **`<li>`**: Defines an individual item inside a `<ul>`, `<ol>`, or `<menu>`.
* **`<menu>`**: Marks an unordered list of interactive commands or toolbar buttons.
* **`<dl>`**: Wraps a description list of terms and their definitions.
* **`<dt>`**: Identifies the term or name being defined inside a `<dl>`.
* **`<dd>`**: Provides the description or value for the preceding `<dt>`.
* **`<table>`**: Defines tabular data organized into rows and columns.
* **`<caption>`**: Adds an accessible title or summary to a `<table>`.
* **`<thead>`**: Groups header rows within a table.
* **`<tbody>`**: Groups main body data rows within a table.
* **`<tfoot>`**: Groups summary or footer rows within a table.
* **`<tr>`**: Defines a row of cells in a table.
* **`<th>`**: Defines a header cell in a table.
* **`<td>`**: Defines a standard data cell in a table.
* **`<colgroup>`**: Groups one or more columns in a table for formatting.
* **`<col>`**: Defines column properties for each column within a `<colgroup>`.

## 4. Inline Text Semantics
* **`<a>`**: Creates a hyperlink to another page, file, or anchor point.
* **`<strong>`**: Indicates text with strong importance, seriousness, or urgency.
* **`<em>`**: Adds stress emphasis that changes the spoken meaning of a sentence.
* **`<mark>`**: Highlights text for reference or relevance within a specific context.
* **`<cite>`**: Marks the title of a creative work (e.g., a book, song, movie, or research paper).
* **`<q>`**: Wraps a short inline quotation.
* **`<abbr>`**: Identifies an abbreviation or acronym, optionally providing the full expansion via the `title` attribute.
* **`<dfn>`**: Marks the specific instance where a term is being defined in a sentence.
* **`<time>`**: Represents a specific date, time, or duration in a machine-readable format (`datetime`).
* **`<data>`**: Links visible content with a machine-readable translation via the `value` attribute.
* **`<code>`**: Marks a short fragment of computer code.
* **`<var>`**: Represents the name of a variable in a mathematical expression or programming context.
* **`<samp>`**: Represents sample or quoted output from a computer program.
* **`<kbd>`**: Represents user input from a keyboard, voice input, or other text entry device.
* **`<sub>`**: Defines subscript text (e.g., chemical formulas like `H2O`).
* **`<sup>`**: Defines superscript text (e.g., exponents or footnotes).
* **`<small>`**: Represents side comments, disclaimers, and fine print.
* **`<s>`**: Marks content that is no longer accurate or relevant (e.g., a strikethrough price).
* **`<del>`**: Tracks text that has been deleted from a document.
* **`<ins>`**: Tracks text that has been added to a document.
* **`<ruby>`**: Represents small annotations rendered above, below, or next to base text (often used for East Asian typography).
* **`<rt>`**: Specifies the ruby text component of a `<ruby>` annotation.
* **`<rp>`**: Provides fallback parentheses for browsers that do not support `<ruby>` annotations.
* **`<bdi>`**: Isolates a span of text that might be formatted in a different text direction from surrounding text.
* **`<bdo>`**: Overrides the current text directionality (left-to-right or right-to-left).
* **`<wbr>`**: Suggests an optional line-break opportunity inside long words or URLs.

## 5. Media & Embedded Content
* **`<figure>`**: Wraps self-contained media (images, diagrams, code snippets) referenced as a single unit.
* **`<figcaption>`**: Provides a caption or legend describing the contents of its parent `<figure>`.
* **`<img>`**: Embeds an image, using the `alt` attribute to provide a text description for bots and screen readers.
* **`<picture>`**: Contains zero or more `<source>` elements and one `<img>` element to offer responsive image versions.
* **`<source>`**: Specifies multiple media resources for `<picture>`, `<audio>`, or `<video>` elements.
* **`<video>`**: Embeds a native media player for video playback.
* **`<audio>`**: Embeds a native sound player for audio content.
* **`<track>`**: Attaches timed text tracks (subtitles, captions, chapters) to `<video>` and `<audio>`.
* **`<iframe>`**: Embeds another HTML page inside the current document.
* **`<svg>`**: Defines a container for Scalable Vector Graphics.
* **`<math>`**: Top-level element for rendering MathML mathematical formulas.
* **`<map>`**: Defines an image map with clickable link areas.
* **`<area>`**: Defines a clickable hotspot region inside an image `<map>`.

## 6. Interactive & Disclosure Elements
* **`<details>`**: Creates a native disclosure widget in which information is visible only when toggled open.
* **`<summary>`**: Specifies a visible heading or toggle label for a `<details>` element.
* **`<dialog>`**: Represents a modal or non-modal dialog box or popup window.

## 7. Forms & User Input
* **`<form>`**: Wraps interactive controls for submitting user information.
* **`<label>`**: Binds a descriptive text caption directly to a form control.
* **`<fieldset>`**: Groups several related controls and labels within a web form.
* **`<legend>`**: Represents a caption for the content of its parent `<fieldset>`.
* **`<input>`**: Creates interactive controls for web-based forms to accept data from the user.
* **`<textarea>`**: Represents a multi-line plain-text editing control.
* **`<select>`**: Represents a control that provides a menu of options.
* **`<option>`**: Defines an item contained in a `<select>`, `<optgroup>`, or `<datalist>`.
* **`<optgroup>`**: Creates a grouping of options within a `<select>` element.
* **`<button>`**: Creates an interactive button for submitting forms or triggering actions.
* **`<datalist>`**: Contains a set of `<option>` elements that represent permissible or recommended choices for other controls.
* **`<output>`**: Container element into which a site or app can inject the results of a calculation or user action.
* **`<progress>`**: Displays an indicator showing the completion progress of a task.
* **`<meter>`**: Represents either a scalar value within a known range or a fractional value.

---

## Reference: Non-Semantic Tags (Avoid for Structure)
* **`<div>`**: Generic block-level container with no semantic meaning (use only for layout/CSS styling hooks).
* **`<span>`**: Generic inline container with no semantic meaning (use only for styling hooks).
* **`<b>`, `<i>`, `<u>`**: Legacy formatting tags that change visual style without signaling importance or emphasis.
