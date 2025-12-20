export type DocumentStatus = 
  | "inbox" 
  | "draft" 
  | "review" 
  | "approved" 
  | "submitted" 
  | "awarded" 
  | "reporting" 
  | "closed";

export interface Revision {
  id: string;
  number: number;
  date: string;
  author: string;
  notes: string;
  fileSize: string;
}

export interface Document {
  id: string;
  name: string;
  type: string;
  status: DocumentStatus;
  destination?: string;
  tags: string[];
  uploadedAt: string;
  uploadedBy: string;
  fileSize: string;
  currentRevision: number;
  revisions: Revision[];
  extractedText?: string;
  metadata?: {
    projectCode?: string;
    documentType?: string;
    deadline?: string;
  };
}

export interface Destination {
  id: string;
  name: string;
  path: string;
  color: string;
  icon: string;
  count: number;
}

export interface AutoRoutingRule {
  id: string;
  name: string;
  condition: string;
  destination: string;
  tags: string[];
  active: boolean;
}

export const mockDocuments: Document[] = [
  {
    id: "doc-1",
    name: "Annex A2 - Partnership Agreement.pdf",
    type: "pdf",
    status: "inbox",
    tags: [],
    uploadedAt: "2025-12-19 14:30",
    uploadedBy: "John Smith",
    fileSize: "2.4 MB",
    currentRevision: 1,
    revisions: [
      {
        id: "r1",
        number: 1,
        date: "2025-12-19 14:30",
        author: "John Smith",
        notes: "Initial upload",
        fileSize: "2.4 MB"
      }
    ]
  },
  {
    id: "doc-2",
    name: "Budget-2025-IPA-final.xlsx",
    type: "xlsx",
    status: "inbox",
    tags: [],
    uploadedAt: "2025-12-19 13:15",
    uploadedBy: "Maria Garcia",
    fileSize: "1.8 MB",
    currentRevision: 1,
    revisions: [
      {
        id: "r1",
        number: 1,
        date: "2025-12-19 13:15",
        author: "Maria Garcia",
        notes: "Initial upload",
        fileSize: "1.8 MB"
      }
    ]
  },
  {
    id: "doc-3",
    name: "Guidelines for Applicants - EU Health 2025.pdf",
    type: "pdf",
    status: "inbox",
    tags: [],
    uploadedAt: "2025-12-19 11:45",
    uploadedBy: "Ahmed Hassan",
    fileSize: "4.2 MB",
    currentRevision: 1,
    revisions: [
      {
        id: "r1",
        number: 1,
        date: "2025-12-19 11:45",
        author: "Ahmed Hassan",
        notes: "Initial upload",
        fileSize: "4.2 MB"
      }
    ],
    extractedText: "Guidelines for Applicants\nEU Health Programme 2025\nCall for Proposals\nDeadline: 15 March 2026..."
  },
  {
    id: "doc-4",
    name: "Proposal - Digital Transformation.docx",
    type: "docx",
    status: "approved",
    destination: "EU Projects",
    tags: ["Full Application", "IPA-2025-DT"],
    uploadedAt: "2025-12-15 09:20",
    uploadedBy: "Lisa Chen",
    fileSize: "3.1 MB",
    currentRevision: 4,
    revisions: [
      {
        id: "r4",
        number: 4,
        date: "2025-12-18 16:30",
        author: "Lisa Chen",
        notes: "Final review changes - approved by committee",
        fileSize: "3.1 MB"
      },
      {
        id: "r3",
        number: 3,
        date: "2025-12-17 10:15",
        author: "Ahmed Hassan",
        notes: "Budget section revision",
        fileSize: "3.0 MB"
      },
      {
        id: "r2",
        number: 2,
        date: "2025-12-16 14:00",
        author: "Maria Garcia",
        notes: "Updated methodology section",
        fileSize: "2.9 MB"
      },
      {
        id: "r1",
        number: 1,
        date: "2025-12-15 09:20",
        author: "Lisa Chen",
        notes: "Initial draft",
        fileSize: "2.7 MB"
      }
    ],
    metadata: {
      projectCode: "IPA-2025-DT",
      documentType: "Full Application",
      deadline: "2026-01-30"
    }
  },
  {
    id: "doc-5",
    name: "Logframe - Rural Development.xlsx",
    type: "xlsx",
    status: "review",
    destination: "EU Projects",
    tags: ["Logframe", "RDP-2025"],
    uploadedAt: "2025-12-14 15:45",
    uploadedBy: "John Smith",
    fileSize: "892 KB",
    currentRevision: 2,
    revisions: [
      {
        id: "r2",
        number: 2,
        date: "2025-12-18 11:20",
        author: "Ahmed Hassan",
        notes: "Updated indicators and assumptions",
        fileSize: "892 KB"
      },
      {
        id: "r1",
        number: 1,
        date: "2025-12-14 15:45",
        author: "John Smith",
        notes: "Initial logframe structure",
        fileSize: "856 KB"
      }
    ],
    metadata: {
      projectCode: "RDP-2025",
      documentType: "Logframe"
    }
  },
  {
    id: "doc-6",
    name: "Brand Assets - Logo Pack.zip",
    type: "zip",
    status: "inbox",
    tags: [],
    uploadedAt: "2025-12-19 10:00",
    uploadedBy: "Sarah Johnson",
    fileSize: "15.3 MB",
    currentRevision: 1,
    revisions: [
      {
        id: "r1",
        number: 1,
        date: "2025-12-19 10:00",
        author: "Sarah Johnson",
        notes: "Initial upload",
        fileSize: "15.3 MB"
      }
    ]
  },
  {
    id: "doc-7",
    name: "Contract Amendment - Infrastructure.pdf",
    type: "pdf",
    status: "inbox",
    tags: [],
    uploadedAt: "2025-12-19 09:30",
    uploadedBy: "David Lee",
    fileSize: "1.2 MB",
    currentRevision: 1,
    revisions: [
      {
        id: "r1",
        number: 1,
        date: "2025-12-19 09:30",
        author: "David Lee",
        notes: "Initial upload",
        fileSize: "1.2 MB"
      }
    ]
  },
  {
    id: "doc-8",
    name: "IPA-2025-infrastructure-budget-v3.xlsx",
    type: "xlsx",
    status: "inbox",
    tags: [],
    uploadedAt: "2025-12-19 08:15",
    uploadedBy: "Maria Garcia",
    fileSize: "2.1 MB",
    currentRevision: 1,
    revisions: [
      {
        id: "r1",
        number: 1,
        date: "2025-12-19 08:15",
        author: "Maria Garcia",
        notes: "Initial upload",
        fileSize: "2.1 MB"
      }
    ]
  }
];

export const mockDestinations: Destination[] = [
  {
    id: "dest-1",
    name: "EU Projects",
    path: "/EU Projects",
    color: "blue",
    icon: "Briefcase",
    count: 24
  },
  {
    id: "dest-2",
    name: "Calls & Guidelines",
    path: "/Calls",
    color: "purple",
    icon: "Bell",
    count: 12
  },
  {
    id: "dest-3",
    name: "Finance / Budgets",
    path: "/Finance/Budgets",
    color: "green",
    icon: "DollarSign",
    count: 18
  },
  {
    id: "dest-4",
    name: "Legal Documents",
    path: "/Legal",
    color: "red",
    icon: "Scale",
    count: 8
  },
  {
    id: "dest-5",
    name: "Brand Assets",
    path: "/Brand",
    color: "orange",
    icon: "Palette",
    count: 35
  },
  {
    id: "dest-6",
    name: "Client Files",
    path: "/Clients",
    color: "pink",
    icon: "Users",
    count: 42
  }
];

export const mockRoutingRules: AutoRoutingRule[] = [
  {
    id: "rule-1",
    name: "EU Annexes → Full Application",
    condition: 'Filename contains "Annex A"',
    destination: "EU Projects",
    tags: ["Full Application"],
    active: true
  },
  {
    id: "rule-2",
    name: "Budget Files → Finance",
    condition: 'Extension .xlsx AND filename contains "budget"',
    destination: "Finance / Budgets",
    tags: ["Budget"],
    active: true
  },
  {
    id: "rule-3",
    name: "Guidelines → Calls Library",
    condition: 'Text contains "Guidelines for Applicants"',
    destination: "Calls & Guidelines",
    tags: ["Guidelines"],
    active: true
  },
  {
    id: "rule-4",
    name: "Project Code Detection",
    condition: 'Text matches pattern IPA-\\d{4}-[A-Z]+',
    destination: "EU Projects",
    tags: [],
    active: true
  },
  {
    id: "rule-5",
    name: "Contracts → Legal",
    condition: 'Filename contains "contract" OR "agreement"',
    destination: "Legal Documents",
    tags: ["Contract"],
    active: true
  }
];

export interface Project {
  id: string;
  code: string;
  name: string;
  status: "planning" | "proposal" | "awarded" | "implementation" | "reporting" | "closed";
  programme: string;
  deadline?: string;
  budget: string;
  documents: number;
}

export const mockProjects: Project[] = [
  {
    id: "proj-1",
    code: "IPA-2025-DT",
    name: "Digital Transformation Initiative",
    status: "proposal",
    programme: "IPA III",
    deadline: "2026-01-30",
    budget: "€2.4M",
    documents: 12
  },
  {
    id: "proj-2",
    code: "RDP-2025",
    name: "Rural Development Programme",
    status: "implementation",
    programme: "EAFRD",
    budget: "€3.8M",
    documents: 28
  },
  {
    id: "proj-3",
    code: "ESF-2024-YE",
    name: "Youth Employment Initiative",
    status: "reporting",
    programme: "ESF+",
    deadline: "2025-12-30",
    budget: "€1.5M",
    documents: 45
  },
  {
    id: "proj-4",
    code: "HEALTH-2025-RHN",
    name: "Regional Healthcare Network",
    status: "proposal",
    programme: "EU Health",
    deadline: "2025-12-25",
    budget: "€5.2M",
    documents: 8
  }
];

export interface Proposal {
  id: string;
  title: string;
  projectCode: string;
  stage: "concept-note" | "full-application" | "submitted" | "awarded" | "rejected";
  programme: string;
  deadline: string;
  leadDocument?: string;
  lastUpdated: string;
}

export const mockProposals: Proposal[] = [
  {
    id: "prop-1",
    title: "Digital Transformation Initiative",
    projectCode: "IPA-2025-DT",
    stage: "full-application",
    programme: "IPA III",
    deadline: "2026-01-30",
    leadDocument: "doc-4",
    lastUpdated: "2025-12-18"
  },
  {
    id: "prop-2",
    title: "Regional Healthcare Network",
    projectCode: "HEALTH-2025-RHN",
    stage: "full-application",
    programme: "EU Health",
    deadline: "2025-12-25",
    lastUpdated: "2025-12-17"
  },
  {
    id: "prop-3",
    title: "Green Energy Transition",
    projectCode: "LIFE-2025-GET",
    stage: "concept-note",
    programme: "LIFE Programme",
    deadline: "2026-02-15",
    lastUpdated: "2025-12-10"
  },
  {
    id: "prop-4",
    title: "Cultural Heritage Preservation",
    projectCode: "CEF-2025-CHP",
    stage: "submitted",
    programme: "Creative Europe",
    deadline: "2025-11-30",
    lastUpdated: "2025-11-28"
  }
];
