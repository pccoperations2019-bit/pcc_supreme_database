/* global fetch, FormData, setTimeout */
import { usePage } from '@inertiajs/vue3'

export function useDairyBoxDocuments() {
  const page = usePage()
  const csrfToken = page.props.csrf_token

  async function getPresignedUrl(file) {
    const response = await fetch('/xhr/files-presigned-url', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
      body: JSON.stringify({ filename: file.name, contentType: file.type }),
    })

    if (!response.ok) throw new Error(`Failed to get presigned URL: ${response.status}`)
    return response.json()
  }

  async function putToPresignedUrl(url, file) {
    const response = await fetch(url, {
      method: 'PUT',
      headers: { 'Content-Type': file.type || 'application/octet-stream' },
      body: file,
    })

    if (!response.ok) throw new Error(`File upload failed: ${response.status}`)
  }

  /**
   * Upload a file to storage and return the metadata needed by a later form submission.
   */
  async function stageDocument(file) {
    const presigned = await getPresignedUrl(file)
    await putToPresignedUrl(presigned.url, file)

    return {
      file_name: file.name,
      file_key: presigned.key,
      file_type: file.type || 'application/octet-stream',
      file_size: file.size,
    }
  }

  /**
   * Upload a compliance document for a DairyBox.
   * Tags it with key=dairy_box / value=dairyBoxId and key=document / value=documentType.
   */
  async function uploadDocument({
    dairyBoxId,
    file,
    documentType,
    description,
    registrationNumber,
    validity,
    complianceStatus,
  }) {
    const staged = await stageDocument(file)

    const formData = new FormData()
    formData.append('title', staged.file_name)
    formData.append('file_name', staged.file_name)
    formData.append('file_key', staged.file_key)
    formData.append('file_type', staged.file_type)
    formData.append('file_size', staged.file_size)
    formData.append('description', description || '')
    formData.append('registration_number', registrationNumber || '')
    formData.append('validity', validity || '')
    formData.append('compliance_status', complianceStatus || '')
    ;[
      { key: 'dairy_box', value: dairyBoxId },
      { key: 'document', value: documentType },
    ].forEach((tag, index) => {
      formData.append(`tags[${index}][key]`, tag.key)
      formData.append(`tags[${index}][value]`, tag.value)
    })

    const response = await fetch('/documents', {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' },
      body: formData,
    })

    if (!response.ok) throw new Error(`Failed to create document: ${response.status}`)
    return response.json()
  }

  /**
   * Update an existing compliance document.
   */
  async function updateDocument(
    id,
    {
      dairyBoxId,
      file,
      documentType,
      description,
      registrationNumber,
      validity,
      title,
      complianceStatus,
    }
  ) {
    let presigned = null
    if (file) {
      presigned = await getPresignedUrl(file)
      await putToPresignedUrl(presigned.url, file)
    }

    const formData = new FormData()
    formData.append('_method', 'PUT')
    formData.append('title', title || '')
    formData.append('description', description || '')
    formData.append('registration_number', registrationNumber || '')
    formData.append('validity', validity || '')
    formData.append('compliance_status', complianceStatus || '')
    formData.append('document_type', documentType || '')
    formData.append('dairy_box_id', dairyBoxId || '')

    if (presigned && file) {
      formData.append('file_name', file.name)
      formData.append('file_key', presigned.key)
      formData.append('file_type', file.type)
      formData.append('file_size', file.size)
    }

    const response = await fetch(`/documents/${id}`, {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' },
      body: formData,
    })

    if (!response.ok) throw new Error(`Failed to update document: ${response.status}`)
  }

  function viewDocument(doc) {
    if (doc?.file_path) {
      window.open(`/${doc.file_path}`, '_blank')
    }
  }

  async function downloadDocument(doc) {
    try {
      if (!doc?.file_path) return

      const response = await fetch(`/${doc.file_path}`)
      const blob = await response.blob()
      const url = window.URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = url
      link.download = doc.file_name || doc.title || 'document'
      link.style.display = 'none'
      document.body.appendChild(link)
      link.click()
      setTimeout(() => {
        document.body.removeChild(link)
        window.URL.revokeObjectURL(url)
      }, 100)
    } catch {
      viewDocument(doc)
    }
  }

  return { stageDocument, uploadDocument, updateDocument, viewDocument, downloadDocument }
}
