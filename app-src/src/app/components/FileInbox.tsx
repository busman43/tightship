import { useState } from "react";
import { mockDocuments, mockDestinations, mockRoutingRules, Document, Destination } from "../data/mockData";
import { Button } from "./ui/button";
import { Card } from "./ui/card";
import { 
  FileText, 
  Filter, 
  Search, 
  ChevronRight,
  Upload,
  Wand2,
  Settings,
  File,
  FileSpreadsheet,
  Archive
} from "lucide-react";
import { Input } from "./ui/input";

interface FileInboxProps {
  onDocumentSelect: (docId: string) => void;
}

export function FileInbox({ onDocumentSelect }: FileInboxProps) {
  const [documents] = useState<Document[]>(mockDocuments.filter(d => d.status === "inbox"));
  const [destinations] = useState<Destination[]>(mockDestinations);
  const [showRoutingRules, setShowRoutingRules] = useState(false);

  const handleAutoRoute = () => {
    // Simulate auto-routing
    alert("Auto-routing would apply rules to classify these 8 files based on filename patterns, content, and metadata.");
  };

  const getFileIcon = (type: string) => {
    switch (type) {
      case "pdf":
        return <File className="w-5 h-5 text-red-500" />;
      case "docx":
        return <FileText className="w-5 h-5 text-blue-500" />;
      case "xlsx":
        return <FileSpreadsheet className="w-5 h-5 text-green-500" />;
      case "zip":
        return <Archive className="w-5 h-5 text-purple-500" />;
      default:
        return <File className="w-5 h-5 text-gray-500" />;
    }
  };

  return (
    <div className="h-full flex flex-col bg-white">
      {/* Header */}
      <div className="border-b border-gray-200 p-6">
        <div className="flex items-center justify-between mb-4">
          <div>
            <h1 className="text-2xl font-semibold text-gray-900">File Inbox</h1>
            <p className="text-gray-600 mt-1">8 files waiting to be classified</p>
          </div>
          <div className="flex items-center gap-2">
            <Button variant="outline" onClick={() => setShowRoutingRules(!showRoutingRules)}>
              <Settings className="w-4 h-4 mr-2" />
              Routing Rules
            </Button>
            <Button onClick={handleAutoRoute} className="bg-blue-600 hover:bg-blue-700">
              <Wand2 className="w-4 h-4 mr-2" />
              Auto-Route All
            </Button>
            <Button variant="outline">
              <Upload className="w-4 h-4 mr-2" />
              Upload
            </Button>
          </div>
        </div>

        {/* Search & Filter */}
        <div className="flex items-center gap-3">
          <div className="flex-1 relative">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
            <Input 
              placeholder="Search files..." 
              className="pl-9"
            />
          </div>
          <Button variant="outline">
            <Filter className="w-4 h-4 mr-2" />
            Filter
          </Button>
        </div>
      </div>

      {/* Routing Rules Panel (toggleable) */}
      {showRoutingRules && (
        <div className="border-b border-gray-200 bg-blue-50 p-6">
          <div className="flex items-start justify-between mb-4">
            <div>
              <h3 className="font-semibold text-gray-900 mb-1">Active Routing Rules</h3>
              <p className="text-sm text-gray-600">Files are automatically classified based on these patterns</p>
            </div>
          </div>
          <div className="space-y-2">
            {mockRoutingRules.map(rule => (
              <div key={rule.id} className="bg-white rounded-lg p-3 flex items-center justify-between">
                <div className="flex-1">
                  <div className="font-medium text-sm text-gray-900">{rule.name}</div>
                  <div className="text-xs text-gray-600 mt-0.5">
                    {rule.condition} → {rule.destination}
                    {rule.tags.length > 0 && ` + Tags: ${rule.tags.join(", ")}`}
                  </div>
                </div>
                <div className={`w-2 h-2 rounded-full ${rule.active ? 'bg-green-500' : 'bg-gray-300'}`} />
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Main Content */}
      <div className="flex-1 overflow-hidden">
        <div className="grid grid-cols-1 lg:grid-cols-2 h-full">
          {/* Files List */}
          <div className="border-r border-gray-200 overflow-y-auto">
            <div className="p-4">
              <div className="text-sm font-medium text-gray-600 mb-3 px-2">Unclassified Files</div>
              <div className="space-y-2">
                {documents.map((doc) => (
                  <Card 
                    key={doc.id}
                    className="p-4 hover:shadow-md transition-all cursor-pointer border border-gray-200"
                    onClick={() => onDocumentSelect(doc.id)}
                  >
                    <div className="flex items-start gap-3">
                      <div className="mt-0.5">
                        {getFileIcon(doc.type)}
                      </div>
                      <div className="flex-1 min-w-0">
                        <div className="font-medium text-gray-900 mb-1 truncate">{doc.name}</div>
                        <div className="flex items-center gap-2 text-xs text-gray-500">
                          <span>{doc.fileSize}</span>
                          <span>•</span>
                          <span>{doc.uploadedBy}</span>
                          <span>•</span>
                          <span>{doc.uploadedAt}</span>
                        </div>
                      </div>
                      <ChevronRight className="w-5 h-5 text-gray-400" />
                    </div>
                  </Card>
                ))}
              </div>
            </div>
          </div>

          {/* Destinations */}
          <div className="overflow-y-auto bg-gray-50">
            <div className="p-4">
              <div className="text-sm font-medium text-gray-600 mb-3 px-2">Quick Destinations</div>
              <div className="space-y-2">
                {destinations.map((dest) => (
                  <Card 
                    key={dest.id}
                    className="p-4 bg-white border border-gray-200 hover:shadow-md transition-all cursor-pointer"
                  >
                    <div className="flex items-center justify-between">
                      <div className="flex items-center gap-3">
                        <div className={`w-10 h-10 bg-${dest.color}-100 rounded-lg flex items-center justify-center`}>
                          <div className={`w-5 h-5 bg-${dest.color}-500 rounded`} />
                        </div>
                        <div>
                          <div className="font-medium text-gray-900">{dest.name}</div>
                          <div className="text-xs text-gray-500">{dest.path}</div>
                        </div>
                      </div>
                      <div className="text-sm font-medium text-gray-600">
                        {dest.count} files
                      </div>
                    </div>
                  </Card>
                ))}
              </div>

              {/* Inbox Zero Tip */}
              <Card className="mt-6 p-4 bg-gradient-to-br from-blue-50 to-indigo-50 border border-blue-200">
                <div className="text-sm font-medium text-blue-900 mb-1">💡 Inbox Zero Tip</div>
                <p className="text-xs text-blue-700">
                  Use Auto-Route to automatically classify files based on patterns. 
                  Files matching your rules will be moved to destinations and tagged instantly.
                </p>
              </Card>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
