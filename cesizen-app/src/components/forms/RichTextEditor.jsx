import { Editor } from '@tinymce/tinymce-react'
import 'tinymce/tinymce'
import 'tinymce/icons/default'
import 'tinymce/models/dom'
import 'tinymce/themes/silver'
import 'tinymce/plugins/advlist'
import 'tinymce/plugins/autolink'
import 'tinymce/plugins/code'
import 'tinymce/plugins/image'
import 'tinymce/plugins/link'
import 'tinymce/plugins/lists'
import 'tinymce/plugins/preview'
import 'tinymce/plugins/table'
import 'tinymce/plugins/wordcount'
import 'tinymce/skins/ui/oxide/skin.min.css'
import 'tinymce/skins/content/default/content.min.css'

export default function RichTextEditor({ id, label, value, onChange, required = false }) {
  return (
    <label className="form-field">
      <span>{label}</span>
      <div className="rich-text-editor">
        <Editor
          id={id}
          licenseKey="gpl"
          value={value}
          onEditorChange={onChange}
          init={{
            menubar: false,
            branding: false,
            height: 420,
            plugins: ['advlist', 'autolink', 'lists', 'link', 'image', 'table', 'preview', 'wordcount', 'code'],
            toolbar:
              'undo redo | blocks | bold italic underline | forecolor | bullist numlist | link image table | alignleft aligncenter alignright | blockquote | removeformat | preview code',
            block_formats: 'Paragraphe=p; Titre 2=h2; Titre 3=h3; Citation=blockquote',
            content_style:
              "body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 16px; line-height: 1.7; color: #16324f; }",
            browser_spellcheck: true,
            contextmenu: false,
            promotion: false,
            statusbar: true,
          }}
        />
      </div>
      {required ? <input type="hidden" value={value} required readOnly /> : null}
    </label>
  )
}
