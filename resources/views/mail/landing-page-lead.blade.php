New enquiry from your landing page "{{ $pageName }}"

Name: {{ $lead->name }}
Email: {{ $lead->email ?? '-' }}
Phone: {{ $lead->phone ?? '-' }}

{{ $lead->message }}
