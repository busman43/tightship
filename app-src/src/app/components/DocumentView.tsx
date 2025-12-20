import { useState } from "react";
import { mockDocuments, Document, DocumentStatus } from "../data/mockData";
import { Button } from "./ui/button";
import { Card } from "./ui/card";
import { 
  ArrowLeft, 
  Download, 
  Share2, 
  MoreVertical,
  History,
  Tag,
  FolderOpen,
  Clock,
  User,
  FileText,
  ChevronRight
} from "lucide-react";
import { Badge } from "./ui/badge";

interface DocumentViewProps {
  documentId: string;
  onBack: () => void;
}

const statusFlow: DocumentStatus[] = ["inbox", "draft", "review", "approved", "submitted", "awarded", "reporting", "closed"];

const statusColors: Record<DocumentStatus, string> = {
  inbox: "bg-gray-100 text-gray-700",
  draft: "bg-blue-100 text-blue-700",
  review: "bg-yellow-100 text-yellow-700",
  approved: "bg-green-100 text-green-700",
  submitted: "bg-purple-100 text-purple-700",
  awarded: "bg-emerald-100 text-emerald-700",
  reporting: "bg-orange-100 text-orange-700",
  closed: "bg-slate-100 text-slate-700"
};

export function DocumentView({ documentId, onBack }: DocumentViewProps) {
  const document = mockDocuments.find(d => d.id === documentId);
  const [showRevisions, setShowRevisions] = useState(false);

  if (!document) {
    return (
      <div className="h-full flex items-center justify-center">
        <div className="text-center">
          <p className="text-gray-600">Document not found</p>
          <Button onClick={onBack} className="mt-4">Back to Inbox</Button>
        </div>
      </div>
    );
  }

  const currentStatusIndex = statusFlow.indexOf(document.status);

  const handleStatusChange = (newStatus: DocumentStatus) => {
    alert(`Status would change from "${document.status}" to "${newStatus}". This would create an audit log entry.`);
  };

  const handleAddRevision = () => {
    alert("This would open a file upload dialog to add a new revision (r" + (document.currentRevision + 1) + ")");
  };

  return (
    <div className="h-full flex flex-col bg-white">
      {/* Header */}
      <div className="border-b border-gray-200 p-6">
        <div className="flex items-center gap-4 mb-4">
          <Button variant="ghost" onClick={onBack} className="gap-2">
            <ArrowLeft className="w-4 h-4" />
            Back
          </Button>
          <div className="h-6 w-px bg-gray-300" />
          <div className="flex-1">
            <h1 className="text-xl font-semibold text-gray-900">{document.name}</h1>
          </div>
          <Button variant="outline">
            <Share2 className="w-4 h-4 mr-2" />
            Share
          </Button>
          <Button variant="outline">
            <Download className="w-4 h-4 mr-2" />
            Download
          </Button>
          <Button variant="ghost" size="icon">
            <MoreVertical className="w-4 h-4" />
          </Button>
        </div>

        {/* Status Flow */}
        <div className="bg-gray-50 rounded-lg p-4">
          <div className="text-xs font-medium text-gray-600 mb-3">Document Workflow</div>
          <div className="flex items-center gap-2">
            {statusFlow.map((status, index) => {
              const isCurrent = status === document.status;
              const isPast = index < currentStatusIndex;
              const isNext = index === currentStatusIndex + 1;

              return (
                <div key={status} className="flex items-center">
                  <button
                    onClick={() => handleStatusChange(status)}
                    disabled={!isNext && !isCurrent}
                    className={`
                      px-3 py-1.5 rounded-md text-xs font-medium transition-all
                      ${isCurrent ? statusColors[status] + ' ring-2 ring-offset-2 ring-blue-500' : ''}
                      ${isPast ? 'bg-gray-200 text-gray-500' : ''}
                      ${isNext ? 'bg-white border-2 border-blue-400 text-blue-700 hover:bg-blue-50' : ''}
                      ${!isCurrent && !isPast && !isNext ? 'bg-gray-100 text-gray-400' : ''}
                      ${isNext ? 'cursor-pointer' : 'cursor-default'}
                    `}
                  >
                    {status.charAt(0).toUpperCase() + status.slice(1)}
                  </button>
                  {index < statusFlow.length - 1 && (
                    <ChevronRight className="w-4 h-4 text-gray-400 mx-1" />
                  )}
                </div>
              );
            })}
          </div>
          <div className="mt-3 text-xs text-gray-600">
            {currentStatusIndex < statusFlow.length - 1 && (
              <>Click <span className="font-medium text-blue-700">{statusFlow[currentStatusIndex + 1]}</span> to advance workflow</>
            )}
            {currentStatusIndex === statusFlow.length - 1 && (
              <>This document has completed the workflow</>
            )}
          </div>
        </div>
      </div>

      {/* Content */}
      <div className="flex-1 overflow-hidden">
        <div className="grid grid-cols-1 lg:grid-cols-3 h-full">
          {/* Main Content Area */}
          <div className="lg:col-span-2 border-r border-gray-200 overflow-y-auto p-6">
            {/* Document Info */}
            <Card className="p-6 border border-gray-200 mb-6">
              <h2 className="font-semibold text-gray-900 mb-4">Document Information</h2>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <div className="text-xs text-gray-500 mb-1">Uploaded By</div>
                  <div className="flex items-center gap-2">
                    <User className="w-4 h-4 text-gray-400" />
                    <span className="text-sm text-gray-900">{document.uploadedBy}</span>
                  </div>
                </div>
                <div>
                  <div className="text-xs text-gray-500 mb-1">Upload Date</div>
                  <div className="flex items-center gap-2">
                    <Clock className="w-4 h-4 text-gray-400" />
                    <span className="text-sm text-gray-900">{document.uploadedAt}</span>
                  </div>
                </div>
                <div>
                  <div className="text-xs text-gray-500 mb-1">File Size</div>
                  <div className="flex items-center gap-2">
                    <FileText className="w-4 h-4 text-gray-400" />
                    <span className="text-sm text-gray-900">{document.fileSize}</span>
                  </div>
                </div>
                <div>
                  <div className="text-xs text-gray-500 mb-1">Current Revision</div>
                  <div className="text-sm font-medium text-gray-900">r{document.currentRevision}</div>
                </div>
              </div>
            </Card>

            {/* Destination & Tags */}
            <Card className="p-6 border border-gray-200 mb-6">
              <h2 className="font-semibold text-gray-900 mb-4">Classification</h2>
              <div className="space-y-4">
                <div>
                  <div className="text-xs text-gray-500 mb-2">Destination</div>
                  {document.destination ? (
                    <div className="flex items-center gap-2 text-sm">
                      <FolderOpen className="w-4 h-4 text-blue-600" />
                      <span className="font-medium text-gray-900">{document.destination}</span>
                    </div>
                  ) : (
                    <Button variant="outline" size="sm">
                      <FolderOpen className="w-4 h-4 mr-2" />
                      Assign Destination
                    </Button>
                  )}
                </div>
                <div>
                  <div className="text-xs text-gray-500 mb-2">Tags</div>
                  <div className="flex items-center gap-2 flex-wrap">
                    {document.tags.length > 0 ? (
                      document.tags.map((tag, i) => (
                        <Badge key={i} variant="secondary">{tag}</Badge>
                      ))
                    ) : (
                      <span className="text-sm text-gray-400">No tags</span>
                    )}
                    <Button variant="outline" size="sm">
                      <Tag className="w-4 h-4 mr-2" />
                      Add Tag
                    </Button>
                  </div>
                </div>
              </div>
            </Card>

            {/* Metadata (if available) */}
            {document.metadata && (
              <Card className="p-6 border border-gray-200 mb-6">
                <h2 className="font-semibold text-gray-900 mb-4">Extracted Metadata</h2>
                <div className="space-y-3">
                  {document.metadata.projectCode && (
                    <div>
                      <div className="text-xs text-gray-500 mb-1">Project Code</div>
                      <div className="text-sm font-mono font-medium text-gray-900">{document.metadata.projectCode}</div>
                    </div>
                  )}
                  {document.metadata.documentType && (
                    <div>
                      <div className="text-xs text-gray-500 mb-1">Document Type</div>
                      <div className="text-sm text-gray-900">{document.metadata.documentType}</div>
                    </div>
                  )}
                  {document.metadata.deadline && (
                    <div>
                      <div className="text-xs text-gray-500 mb-1">Deadline</div>
                      <div className="text-sm text-gray-900">{document.metadata.deadline}</div>
                    </div>
                  )}
                </div>
              </Card>
            )}

            {/* Extracted Text Preview */}
            {document.extractedText && (
              <Card className="p-6 border border-gray-200">
                <h2 className="font-semibold text-gray-900 mb-4">Extracted Text (Searchable)</h2>
                <div className="text-sm text-gray-700 bg-gray-50 p-4 rounded-lg font-mono whitespace-pre-wrap">
                  {document.extractedText.substring(0, 500)}...
                </div>
              </Card>
            )}
          </div>

          {/* Sidebar - Revisions */}
          <div className="bg-gray-50 overflow-y-auto">
            <div className="p-6">
              <div className="flex items-center justify-between mb-4">
                <div className="flex items-center gap-2">
                  <History className="w-5 h-5 text-gray-600" />
                  <h2 className="font-semibold text-gray-900">Revision History</h2>
                </div>
                <button 
                  onClick={() => setShowRevisions(!showRevisions)}
                  className="text-xs text-blue-600 hover:text-blue-700"
                >
                  {showRevisions ? 'Hide' : 'Show All'}
                </button>
              </div>

              <Button 
                onClick={handleAddRevision}
                className="w-full mb-4 bg-blue-600 hover:bg-blue-700"
              >
                Upload New Revision
              </Button>

              <div className="space-y-3">
                {document.revisions.slice(0, showRevisions ? undefined : 3).map((revision, index) => {
                  const isCurrent = revision.number === document.currentRevision;

                  return (
                    <Card 
                      key={revision.id}
                      className={`p-4 bg-white border ${isCurrent ? 'border-blue-500 ring-2 ring-blue-100' : 'border-gray-200'}`}
                    >
                      <div className="flex items-start justify-between mb-2">
                        <div className="font-medium text-gray-900">r{revision.number}</div>
                        {isCurrent && (
                          <Badge className="bg-blue-600">Current</Badge>
                        )}
                      </div>
                      <div className="text-xs text-gray-600 mb-2">{revision.notes}</div>
                      <div className="text-xs text-gray-500">
                        <div>{revision.author}</div>
                        <div>{revision.date}</div>
                        <div className="mt-1">{revision.fileSize}</div>
                      </div>
                      {!isCurrent && (
                        <Button variant="outline" size="sm" className="w-full mt-3">
                          Restore this version
                        </Button>
                      )}
                    </Card>
                  );
                })}
              </div>

              {document.revisions.length > 3 && !showRevisions && (
                <div className="text-center mt-3">
                  <button 
                    onClick={() => setShowRevisions(true)}
                    className="text-sm text-blue-600 hover:text-blue-700"
                  >
                    Show {document.revisions.length - 3} more revisions
                  </button>
                </div>
              )}

              <Card className="mt-6 p-4 bg-gradient-to-br from-green-50 to-emerald-50 border border-green-200">
                <div className="text-sm font-medium text-green-900 mb-1">✓ Immutable History</div>
                <p className="text-xs text-green-700">
                  Every revision is preserved forever. No more "final-final-last.docx" chaos.
                </p>
              </Card>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
