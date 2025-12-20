import { useState } from "react";
import { mockProposals, Proposal, mockDocuments } from "../data/mockData";
import { Card } from "./ui/card";
import { Button } from "./ui/button";
import { Input } from "./ui/input";
import { 
  FileText, 
  Search, 
  Plus, 
  Filter,
  Calendar,
  ExternalLink,
  TrendingUp,
  Zap
} from "lucide-react";
import { Badge } from "./ui/badge";

const stageColors = {
  "concept-note": "bg-blue-100 text-blue-700",
  "full-application": "bg-purple-100 text-purple-700",
  "submitted": "bg-yellow-100 text-yellow-700",
  "awarded": "bg-green-100 text-green-700",
  "rejected": "bg-red-100 text-red-700"
};

const stageLabels = {
  "concept-note": "Concept Note",
  "full-application": "Full Application",
  "submitted": "Submitted",
  "awarded": "Awarded",
  "rejected": "Rejected"
};

export function ProposalsView({ onDocumentSelect }: { onDocumentSelect: (docId: string) => void }) {
  const [proposals] = useState<Proposal[]>(mockProposals);

  const handleOpenDocument = (docId: string) => {
    if (docId) {
      onDocumentSelect(docId);
    } else {
      alert("No lead document attached yet");
    }
  };

  const handleProposalComposer = () => {
    alert("Proposal Composer would open with sections: Context, Relevance, Methodology, M&E, Sustainability, Budget. It would auto-fill from project data and logframe.");
  };

  return (
    <div className="h-full flex flex-col bg-white">
      {/* Header */}
      <div className="border-b border-gray-200 p-6">
        <div className="flex items-center justify-between mb-4">
          <div>
            <h1 className="text-2xl font-semibold text-gray-900">Proposals</h1>
            <p className="text-gray-600 mt-1">Track your proposal pipeline from concept to award</p>
          </div>
          <div className="flex items-center gap-2">
            <Button variant="outline" onClick={handleProposalComposer}>
              <Zap className="w-4 h-4 mr-2" />
              Proposal Composer
            </Button>
            <Button className="bg-blue-600 hover:bg-blue-700">
              <Plus className="w-4 h-4 mr-2" />
              New Proposal
            </Button>
          </div>
        </div>

        {/* Search */}
        <div className="relative">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
          <Input 
            placeholder="Search proposals by title, code, or programme..." 
            className="pl-9"
          />
        </div>
      </div>

      {/* Pipeline Stats */}
      <div className="border-b border-gray-200 bg-gradient-to-r from-blue-50 to-indigo-50 p-6">
        <div className="flex items-center gap-2 mb-3">
          <TrendingUp className="w-5 h-5 text-blue-600" />
          <h2 className="font-semibold text-gray-900">Pipeline Overview</h2>
        </div>
        <div className="grid grid-cols-2 md:grid-cols-5 gap-4">
          <div className="bg-white rounded-lg p-4 border border-blue-200">
            <div className="text-2xl font-semibold text-blue-900">
              {proposals.filter(p => p.stage === "concept-note").length}
            </div>
            <div className="text-xs text-gray-600 mt-1">Concept Notes</div>
          </div>
          <div className="bg-white rounded-lg p-4 border border-purple-200">
            <div className="text-2xl font-semibold text-purple-900">
              {proposals.filter(p => p.stage === "full-application").length}
            </div>
            <div className="text-xs text-gray-600 mt-1">Full Applications</div>
          </div>
          <div className="bg-white rounded-lg p-4 border border-yellow-200">
            <div className="text-2xl font-semibold text-yellow-900">
              {proposals.filter(p => p.stage === "submitted").length}
            </div>
            <div className="text-xs text-gray-600 mt-1">Submitted</div>
          </div>
          <div className="bg-white rounded-lg p-4 border border-green-200">
            <div className="text-2xl font-semibold text-green-900">
              {proposals.filter(p => p.stage === "awarded").length}
            </div>
            <div className="text-xs text-gray-600 mt-1">Awarded</div>
          </div>
          <div className="bg-white rounded-lg p-4 border border-red-200">
            <div className="text-2xl font-semibold text-red-900">
              {proposals.filter(p => p.stage === "rejected").length}
            </div>
            <div className="text-xs text-gray-600 mt-1">Rejected</div>
          </div>
        </div>
      </div>

      {/* Proposals List */}
      <div className="flex-1 overflow-y-auto p-6">
        <div className="max-w-5xl mx-auto space-y-4">
          {proposals.map((proposal) => {
            const leadDoc = proposal.leadDocument 
              ? mockDocuments.find(d => d.id === proposal.leadDocument)
              : null;

            return (
              <Card 
                key={proposal.id}
                className="p-6 border border-gray-200 hover:shadow-md transition-all"
              >
                <div className="flex items-start justify-between gap-6">
                  {/* Main Content */}
                  <div className="flex-1">
                    <div className="flex items-start gap-4 mb-3">
                      <div className="w-12 h-12 bg-gradient-to-br from-blue-500 to-purple-600 rounded-lg flex items-center justify-center flex-shrink-0">
                        <FileText className="w-6 h-6 text-white" />
                      </div>
                      <div className="flex-1">
                        <div className="flex items-center gap-3 mb-1">
                          <h3 className="font-semibold text-gray-900">{proposal.title}</h3>
                          <Badge className={stageColors[proposal.stage]}>
                            {stageLabels[proposal.stage]}
                          </Badge>
                        </div>
                        <div className="flex items-center gap-2 text-sm text-gray-600">
                          <span className="font-mono text-blue-600">{proposal.projectCode}</span>
                          <span>•</span>
                          <span>{proposal.programme}</span>
                        </div>
                      </div>
                    </div>

                    {/* Info Bar */}
                    <div className="flex items-center gap-4 text-sm text-gray-600 mb-4">
                      <div className="flex items-center gap-1.5">
                        <Calendar className="w-4 h-4 text-gray-400" />
                        <span>Deadline: {proposal.deadline}</span>
                      </div>
                      <span>•</span>
                      <span>Updated {proposal.lastUpdated}</span>
                    </div>

                    {/* Lead Document */}
                    {leadDoc && (
                      <div className="bg-gray-50 rounded-lg p-3 flex items-center justify-between">
                        <div>
                          <div className="text-xs text-gray-500 mb-1">Lead Document</div>
                          <div className="font-medium text-sm text-gray-900">{leadDoc.name}</div>
                          <div className="text-xs text-gray-600">
                            Revision r{leadDoc.currentRevision} • {leadDoc.status}
                          </div>
                        </div>
                        <Button 
                          variant="outline" 
                          size="sm"
                          onClick={() => handleOpenDocument(leadDoc.id)}
                        >
                          <ExternalLink className="w-4 h-4" />
                        </Button>
                      </div>
                    )}
                  </div>

                  {/* Actions */}
                  <div className="flex flex-col gap-2">
                    <Button variant="outline" size="sm">
                      View Details
                    </Button>
                    {proposal.stage === "full-application" && (
                      <Button size="sm" className="bg-green-600 hover:bg-green-700">
                        Submit
                      </Button>
                    )}
                  </div>
                </div>
              </Card>
            );
          })}
        </div>

        {/* Info Card */}
        <Card className="max-w-5xl mx-auto mt-6 p-6 bg-gradient-to-br from-purple-50 to-pink-50 border border-purple-200">
          <div className="flex items-start gap-4">
            <div className="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center flex-shrink-0">
              <Zap className="w-5 h-5 text-purple-600" />
            </div>
            <div>
              <div className="font-semibold text-purple-900 mb-1">Proposal Composer</div>
              <p className="text-sm text-purple-700">
                Generate proposal sections (Context, Relevance, Methodology, M&E, Sustainability, Budget) 
                automatically from your project data and logframe. No more copy-paste chaos.
              </p>
            </div>
          </div>
        </Card>
      </div>
    </div>
  );
}
